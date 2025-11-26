<?php
// public/pdf_conversion_diagnostic.php
// Diagnóstico para conversion PDF -> HTML/text
// Uso (navegador): subir un PDF con el formulario o pasar ?path=/ruta/al/archivo.pdf
// Uso (CLI): php pdf_conversion_diagnostic.php /ruta/al/archivo.pdf

// --- Helpers
function ok($v) { return $v ? 'yes' : 'no'; }
function run_cmd($cmd) {
  $out = [];
  $ret = null;
  // intentar exec, shell_exec y proc_open según disponibilidad
  if (function_exists('exec') && stripos(ini_get('disable_functions') ?: '', 'exec') === false) {
    @exec($cmd . ' 2>&1', $out, $ret);
  } else {
    // fallback a shell_exec si está disponible
    if (function_exists('shell_exec') && stripos(ini_get('disable_functions') ?: '', 'shell_exec') === false) {
      $o = @shell_exec($cmd . ' 2>&1');
      $out = $o === null ? [] : explode("\n", trim($o));
      $ret = ($o === null) ? 127 : 0;
    } else {
      // proc_open last-resort
      if (function_exists('proc_open') && stripos(ini_get('disable_functions') ?: '', 'proc_open') === false) {
        $descriptors = [
          1 => ['pipe', 'w'],
          2 => ['pipe', 'w']
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
          $stdout = stream_get_contents($pipes[1]);
          $stderr = stream_get_contents($pipes[2]);
          fclose($pipes[1]); fclose($pipes[2]);
          $ret = proc_close($proc);
          $out = array_filter(array_map('rtrim', explode("\n", $stdout . "\n" . $stderr)));
        } else {
          $out = ['proc_open failed'];
          $ret = 127;
        }
      } else {
        $out = ['No available function to execute commands (exec/shell_exec/proc_open disabled)'];
        $ret = 127;
      }
    }
  }
  return ['out'=>$out, 'ret'=>$ret];
}

function which_cmd($name) {
  // cross-platform: use 'where' on Windows, 'which' on *nix
  $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  $cmd = $isWindows ? "where {$name}" : "which {$name}";
  $r = run_cmd($cmd);
  return ['cmd'=>$cmd, 'out'=>$r['out'], 'ret'=>$r['ret']];
}

// --- Detect input PDF
$pdfPath = null;
$fromCli = (PHP_SAPI === 'cli');
if ($fromCli) {
  global $argv;
  if (!empty($argv[1])) $pdfPath = $argv[1];
} else {
  if (!empty($_GET['path'])) {
    $pdfPath = $_GET['path'];
  } elseif (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    // mover a temp y usarlo
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'diag_upload_' . uniqid() . '.pdf';
    move_uploaded_file($_FILES['file']['tmp_name'], $tmp);
    $pdfPath = $tmp;
  }
}

// --- Gather environment info
$php_version = PHP_VERSION;
$os = PHP_OS;
$cwd = getcwd();
$disabled = ini_get('disable_functions') ?: '';
$disabled_list = array_map('trim', $disabled === '' ? [] : explode(',', $disabled));
$exec_exists = function_exists('exec') && !in_array('exec', $disabled_list);
$shell_exec_exists = function_exists('shell_exec') && !in_array('shell_exec', $disabled_list);
$proc_open_exists = function_exists('proc_open') && !in_array('proc_open', $disabled_list);

// commands to check
$commands = ['pdftohtml', 'pdftotext'];

// --- Run checks
$whichResults = [];
foreach ($commands as $c) $whichResults[$c] = which_cmd($c);

// gather sample conversion outputs if pdf provided
$conversionAttempts = [];
if ($pdfPath !== null && is_file($pdfPath)) {
  // pdftohtml -stdout
  $cmd1 = 'pdftohtml -q -stdout ' . escapeshellarg($pdfPath);
  $r1 = run_cmd($cmd1);
  $conversionAttempts[] = ['method'=>'pdftohtml-stdout','cmd'=>$cmd1,'ret'=>$r1['ret'],'out'=>array_slice($r1['out'],0,60)];

  // pdftotext -layout -
  $cmd2 = 'pdftotext -q -layout ' . escapeshellarg($pdfPath) . ' -';
  $r2 = run_cmd($cmd2);
  $conversionAttempts[] = ['method'=>'pdftotext-layout-stdout','cmd'=>$cmd2,'ret'=>$r2['ret'],'out'=>array_slice($r2['out'],0,60)];

  // pdftohtml -> temp file prefix
  $tmpPrefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdftohtml_' . uniqid();
  $cmd3 = 'pdftohtml -q -nodrm ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($tmpPrefix);
  $r3 = run_cmd($cmd3);
  $filesProduced = glob($tmpPrefix . '*');
  $sampleFileContents = [];
  if (!empty($filesProduced)) {
    foreach ($filesProduced as $i => $f) {
      if ($i >= 5) break;
      $sampleFileContents[] = basename($f) . ' => ' . (is_readable($f) ? substr(file_get_contents($f),0,200) : 'not readable');
    }
  }
  $conversionAttempts[] = ['method'=>'pdftohtml-file','cmd'=>$cmd3,'ret'=>$r3['ret'],'out'=>array_slice($r3['out'],0,60),'files'=>$filesProduced,'sample'=>$sampleFileContents];
}

// --- Output (HTML or CLI)
if ($fromCli) {
  echo "PDF Conversion Diagnostic\n";
  echo "PHP: {$php_version}\nOS: {$os}\nCWD: {$cwd}\n";
  echo "Disabled functions: " . ($disabled === '' ? '(none)' : $disabled) . "\n";
  echo "exec available: " . ok($exec_exists) . "\n";
  echo "shell_exec available: " . ok($shell_exec_exists) . "\n";
  echo "proc_open available: " . ok($proc_open_exists) . "\n\n";
  foreach ($whichResults as $name => $res) {
    echo "Check {$name} (cmd: {$res['cmd']}) -> ret {$res['ret']}\n";
    foreach ($res['out'] as $line) echo "  " . rtrim($line) . "\n";
    echo "\n";
  }
  if (!empty($pdfPath)) {
    echo "PDF path: {$pdfPath}\n";
    foreach ($conversionAttempts as $att) {
      echo "Attempt {$att['method']} (cmd: {$att['cmd']}) ret={$att['ret']}\n";
      foreach ($att['out'] as $ln) echo "  " . rtrim($ln) . "\n";
      if (!empty($att['files'])) {
        echo "Produced files:\n";
        foreach ($att['files'] as $f) echo "  $f\n";
      }
      if (!empty($att['sample'])) {
        echo "Sample file contents (first 200 chars):\n";
        foreach ($att['sample'] as $s) echo "  $s\n";
      }
      echo "\n---\n";
    }
  } else {
    echo "No PDF provided.\n";
  }
  exit(0);
}

// HTML output
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>PDF Conversion Diagnostic</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;font-size:14px;padding:18px} pre{background:#f7f7f7;padding:8px;border:1px solid #ddd;overflow:auto}</style>
</head>
<body>
  <h3>PDF Conversion Diagnostic</h3>

  <p><strong>PHP:</strong> <?= htmlspecialchars($php_version) ?> &nbsp; <strong>OS:</strong> <?= htmlspecialchars($os) ?> &nbsp; <strong>CWD:</strong> <?= htmlspecialchars($cwd) ?></p>

  <h4>Environment</h4>
  <ul>
    <li><strong>Disabled functions:</strong> <?= $disabled === '' ? '<em>(none)</em>' : htmlspecialchars($disabled) ?></li>
    <li><strong>exec available:</strong> <?= ok($exec_exists) ?></li>
    <li><strong>shell_exec available:</strong> <?= ok($shell_exec_exists) ?></li>
    <li><strong>proc_open available:</strong> <?= ok($proc_open_exists) ?></li>
  </ul>

  <h4>Which / where checks</h4>
  <?php foreach ($whichResults as $name => $res): ?>
    <div style="margin-bottom:12px">
      <strong><?= htmlspecialchars($name) ?></strong> — cmd: <code><?= htmlspecialchars($res['cmd']) ?></code> — ret: <?= (int)$res['ret'] ?><br>
      <pre><?php foreach ($res['out'] as $ln) echo htmlspecialchars($ln) . "\n"; ?></pre>
    </div>
  <?php endforeach; ?>

  <h4>Try conversion on a PDF</h4>
  <form method="post" enctype="multipart/form-data">
    <div>
      <label>Upload PDF to test: <input type="file" name="file" accept="application/pdf"></label>
      <button type="submit">Run test</button>
    </div>
    <div style="margin-top:8px;color:#555">Or provide server path via query string: <code>?path=/absolute/path/to/file.pdf</code></div>
  </form>

  <?php if ($pdfPath !== null): ?>
    <h4>PDF provided</h4>
    <p>Path: <code><?= htmlspecialchars($pdfPath) ?></code></p>

    <?php foreach ($conversionAttempts as $att): ?>
      <h5><?= htmlspecialchars($att['method']) ?> — exit <?= (int)$att['ret'] ?></h5>
      <p><code><?= htmlspecialchars($att['cmd']) ?></code></p>
      <pre><?php foreach ($att['out'] as $ln) echo htmlspecialchars($ln) . "\n"; ?></pre>

      <?php if (!empty($att['files'])): ?>
        <p><strong>Files produced (up to 5):</strong></p>
        <ul>
          <?php foreach (array_slice($att['files'],0,5) as $f): ?>
            <li><?= htmlspecialchars(basename($f)) ?> — <?= is_readable($f) ? 'readable' : 'not readable' ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!empty($att['sample'])): ?>
          <p><strong>Sample contents from produced files:</strong></p>
          <?php foreach ($att['sample'] as $s): ?>
            <pre><?= htmlspecialchars($s) ?></pre>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>

      <hr>
    <?php endforeach; ?>

  <?php else: ?>
    <p>No PDF was provided for conversion testing.</p>
  <?php endif; ?>

  <h4>Next steps</h4>
  <ul>
    <li>If exec/shell_exec/proc_open are disabled, enable them in php.ini (remove from disable_functions) and restart the web server.</li>
    <li>Install Poppler (pdftohtml/pdftotext): on Debian/Ubuntu: <code>sudo apt install -y poppler-utils</code>. On Windows download Poppler for Windows and add the bin folder to PATH.</li>
    <li>If you cannot enable exec or install Poppler on the server, convert PDF to HTML locally (pdftohtml) and paste the HTML into the import textarea as workaround.</li>
  </ul>
</body>
</html>