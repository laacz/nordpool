<?php

class View
{
    private string $viewsPath;

    public function __construct(string $viewsPath = __DIR__ . '/../views')
    {
        $this->viewsPath = $viewsPath;
    }

    /**
     * @throws \Exception
     */
    public function render(string $template, array $templateData = []): void
    {
        $path = $this->viewsPath . '/' . $template . '.php';

        if (!file_exists($path)) {
            throw new Exception("View not found: $template");
        }

        extract($templateData, EXTR_SKIP);
        require $path;
    }

    /**
     * @throws \Exception
     */
    public function renderToString(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);

        return ob_get_clean();
    }
}
