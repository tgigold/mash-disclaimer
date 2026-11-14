<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * Renders PHP templates from templates/. A page template is wrapped in
 * templates/layout.php unless the caller asks for the bare template.
 */
final readonly class View
{
    public function __construct(private string $templateDir)
    {
    }

    /** @param array<string, mixed> $data */
    public function page(string $template, array $data = []): string
    {
        $content = $this->render($template, $data);

        return $this->render('layout', ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        // Template names come from code only, never from a request (S-23).
        if (preg_match('/^[a-z0-9_\/-]+$/', $template) !== 1) {
            throw new \InvalidArgumentException('Invalid template name.');
        }

        $file = $this->templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $render($file, $data);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
