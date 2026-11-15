"""Minimal client for the Chrome DevTools Protocol, standard library only.

Browser tests (Q-3) drive a headless Chrome through it, without npm or any
package to install: the CI runner already has Chrome and Python.
"""

import base64
import json
import os
import socket
import struct
import time
import urllib.request


class Browser:
    def __init__(self, debug_port, url):
        request = urllib.request.Request(
            f'http://127.0.0.1:{debug_port}/json/new?{url}', method='PUT'
        )
        tab = json.loads(urllib.request.urlopen(request, timeout=10).read())
        self._socket = self._connect(tab['webSocketDebuggerUrl'])
        self._id = 0
        self.call('Runtime.enable')
        # A new tab first reports about:blank as "complete" – wait for the real page.
        self.wait('location.href !== "about:blank" && document.readyState === "complete"', timeout=15)

    # ---- protocol ----

    def call(self, method, params=None):
        self._id += 1
        message_id = self._id
        self._send({'id': message_id, 'method': method, 'params': params or {}})
        while True:
            message = self._receive()
            if message.get('id') == message_id:
                if 'error' in message:
                    raise RuntimeError(f'{method}: {message["error"]}')
                return message.get('result', {})
            if message.get('method') == 'Runtime.exceptionThrown':
                details = message['params']['exceptionDetails']
                raise AssertionError(f'JavaScript error: {details.get("exception", {}).get("description", details)}')

    def js(self, expression):
        result = self.call('Runtime.evaluate', {
            'expression': expression,
            'returnByValue': True,
            'awaitPromise': True,
        })
        if 'exceptionDetails' in result:
            raise AssertionError(f'Evaluating {expression!r} failed: {result["exceptionDetails"]}')
        return result.get('result', {}).get('value')

    def wait(self, expression, timeout=5.0):
        """Polls until the expression is truthy; returns its value."""
        deadline = time.monotonic() + timeout
        # DOM nodes cannot be returned by value; a found node counts as true.
        wrapped = f'(() => {{ const v = ({expression}); return v instanceof Node ? true : v; }})()'
        while True:
            value = self.js(wrapped)
            if value:
                return value
            if time.monotonic() > deadline:
                raise AssertionError(f'Timed out waiting for: {expression}')
            time.sleep(0.05)

    def type(self, text):
        self.call('Input.insertText', {'text': text})

    def press(self, key):
        codes = {'Enter': 13, 'Escape': 27, 'ArrowDown': 40, 'ArrowUp': 38}
        down = {'type': 'keyDown', 'key': key, 'code': key, 'windowsVirtualKeyCode': codes[key]}
        if key == 'Enter':
            down['text'] = '\r'
        self.call('Input.dispatchKeyEvent', down)
        self.call('Input.dispatchKeyEvent', {'type': 'keyUp', 'key': key, 'code': key, 'windowsVirtualKeyCode': codes[key]})

    # ---- WebSocket, just enough for the protocol ----

    @staticmethod
    def _connect(url):
        host_port, path = url[len('ws://'):].split('/', 1)
        host, port = host_port.split(':')
        sock = socket.create_connection((host, int(port)), timeout=30)
        key = base64.b64encode(os.urandom(16)).decode()
        sock.sendall((
            f'GET /{path} HTTP/1.1\r\nHost: {host_port}\r\nUpgrade: websocket\r\n'
            f'Connection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n'
        ).encode())
        response = b''
        while b'\r\n\r\n' not in response:
            response += sock.recv(1)
        if b' 101 ' not in response.split(b'\r\n', 1)[0]:
            raise RuntimeError('WebSocket handshake failed')
        return sock

    def _send(self, message):
        data = json.dumps(message).encode()
        header = bytearray([0x81])
        length = len(data)
        if length < 126:
            header.append(0x80 | length)
        elif length < 65536:
            header += bytes([0x80 | 126]) + struct.pack('>H', length)
        else:
            header += bytes([0x80 | 127]) + struct.pack('>Q', length)
        mask = os.urandom(4)
        self._socket.sendall(bytes(header) + mask + bytes(b ^ mask[i % 4] for i, b in enumerate(data)))

    def _receive(self):
        head = self._exact(2)
        length = head[1] & 0x7F
        if length == 126:
            length = struct.unpack('>H', self._exact(2))[0]
        elif length == 127:
            length = struct.unpack('>Q', self._exact(8))[0]
        return json.loads(self._exact(length))

    def _exact(self, count):
        data = b''
        while len(data) < count:
            chunk = self._socket.recv(count - len(data))
            if not chunk:
                raise EOFError('Browser closed the connection')
            data += chunk
        return data
