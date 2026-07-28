<?php
namespace App\Module\Notification\Service;

use App\Module\Notification\Repository\NotificationTemplateRepository;

class NotificationTemplateRenderer
{
    public function __construct(
        private readonly NotificationTemplateRepository $templateRepository,
    ) {}

    public function render(string $templateKey, array $variables): array
    {
        $template = $this->templateRepository->findOneBy(['key' => $templateKey]);
        if (!$template) {
            return [
                'subject' => $templateKey,
                'body'    => implode(', ', array_map(
                    fn($k, $v) => "$k: $v",
                    array_keys($variables),
                    $variables
                )),
            ];
        }
        return [
            'subject' => $this->replace($template->getSubjectTemplate() ?? '', $variables),
            'body'    => $this->replace($template->getBodyTemplate(), $variables),
        ];
    }

    private function replace(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', (string) $value, $template);
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }
}
