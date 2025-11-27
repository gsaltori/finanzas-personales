<?php
// app/Services/HtmlCleanerService.php
namespace App\Services;

class HtmlCleanerService
{
    public function clean(string $html): string
    {
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#on[a-z]+=[\'\"](.*?)[\'\"]#i', '', $html);
        $html = preg_replace('#javascript:#i', '', $html);
        return $html;
    }
}
