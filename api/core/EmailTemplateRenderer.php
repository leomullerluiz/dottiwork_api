<?php

class EmailTemplateRenderer
{
    public static function renderHtml($slug, array $variables)
    {
        return self::renderFile(self::templatePath($slug, 'html'), $variables);
    }

    public static function renderText($slug, array $variables)
    {
        $path = self::templatePath($slug, 'txt');
        if (!file_exists($path)) {
            return null;
        }

        return self::renderFile($path, $variables);
    }

    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function renderFile($path, array $variables)
    {
        if (!file_exists($path)) {
            throw new RuntimeException('Template nao encontrado: ' . basename($path));
        }

        $body = file_get_contents($path);
        foreach ($variables as $key => $value) {
            $body = str_replace('{{ ' . $key . ' }}', (string) $value, $body);
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }

        return $body;
    }

    private static function templatePath($slug, $extension)
    {
        return __DIR__ . '/../templates/' . $slug . '.' . $extension;
    }
}
