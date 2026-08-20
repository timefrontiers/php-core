<?php

declare(strict_types=1);

$path = \parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/large-header') {
  \header('X-Large: ' . \str_repeat('h', 2048));
  echo 'ok';
  return;
}

if ($path === '/large-body') {
  echo \str_repeat('b', 2048);
  return;
}

if ($path === '/slow') {
  \usleep(500000);
  echo 'late';
  return;
}

\header('Content-Type: application/json');
echo '{"ok":true}';
