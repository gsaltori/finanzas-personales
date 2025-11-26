<?php
// app/Helpers/PdfConverter.php
// Convierte PDF a HTML/text usando Poppler (pdftohtml / pdftotext).
// No gestiona conexión ni sesión; sólo devuelve contenido y diagnóstico.

class PdfConverter {
  /**
   * Convierte un PDF a HTML (preferido) o a texto (fallback) y retorna:
   * ['ok'=>bool, 'method'=>'pdftohtml|pdftotext|none', 'content'=>string, 'diagnostic'=>array, 'error'=>string|null]
   */
  public static function convertPdfToHtml(string $pdfPath, ?string $popplerBin = null): array {
    $diag = [];
    $pdfPath = realpath($pdfPath) ?: $pdfPath;

    if (!function_exists('exec') || stripos(ini_get('disable_functions') ?: '', 'exec') !== false) {
      $diag[] = 'exec() no disponible en este entorno.';
      return ['ok'=>false, 'method'=>'none', 'content'=>'', 'diagnostic'=>$diag, 'error'=>'exec not available'];
    }

    $binPath = '';
    if (!empty($popplerBin)) $binPath = rtrim($popplerBin, "\\/") . DIRECTORY_SEPARATOR;

    // Preferir pdftohtml
    $cmd = escapeshellcmd($binPath . 'pdftohtml') . ' -q -stdout ' . escapeshellarg($pdfPath) . ' 2>&1';
    $out = []; $ret = null;
    @exec($cmd, $out, $ret);
    $diag[] = "Ejecutado: {$cmd} ; retorno: {$ret}";
    if ($ret === 0 && !empty($out)) {
      $content = implode("\n", $out);
      return ['ok'=>true, 'method'=>'pdftohtml', 'content'=>$content, 'diagnostic'=>$diag, 'error'=>null];
    }

    // Fallback: pdftotext con -layout
    $cmd2 = escapeshellcmd($binPath . 'pdftotext') . ' -layout -enc UTF-8 ' . escapeshellarg($pdfPath) . ' - 2>&1';
    $out2 = []; $ret2 = null;
    @exec($cmd2, $out2, $ret2);
    $diag[] = "Ejecutado: {$cmd2} ; retorno: {$ret2}";
    if ($ret2 === 0 && !empty($out2)) {
      $text = implode("\n", $out2);
      $content = "<pre>" . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
      return ['ok'=>true, 'method'=>'pdftotext', 'content'=>$content, 'diagnostic'=>$diag, 'error'=>null];
    }

    $diag[] = 'Ninguna utilidad devolvió contenido válido.';
    return ['ok'=>false, 'method'=>'none', 'content'=>'', 'diagnostic'=>$diag, 'error'=>'conversion failed'];
  }

  public static function isExecAvailable(): bool {
    return function_exists('exec') && stripos(ini_get('disable_functions') ?: '', 'exec') === false;
  }

  public static function installationHints(): array {
    if (self::isExecAvailable()) {
      return ['exec() disponible. Asegúrate que Poppler (pdftohtml/pdftotext) esté en PATH o especifica poppler_bin en config.'];
    }
    return ['exec() no disponible en PHP; no podrá ejecutarse pdftohtml desde el servidor web.'];
  }
}