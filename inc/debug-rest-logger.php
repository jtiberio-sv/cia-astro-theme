<?php
/**
 * Log de requisições REST (WooCommerce)
 * Salva em wp-content/rest-api.log
 */
if (!defined('ABSPATH')) exit;

add_filter('rest_pre_dispatch', function($result, WP_REST_Server $server, WP_REST_Request $request){
  $route = $request->get_route();
  // monitore apenas WooCommerce (ajuste se quiser)
  if (strpos($route, '/wc/v') === 0) {
    $logFile = WP_CONTENT_DIR . '/rest-api.log';
    $entry = [
      'time'    => current_time('mysql'),
      'route'   => $route,
      'method'  => $request->get_method(),
      'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
      'headers' => $request->get_headers(),
      'params'  => $request->get_params(),       // query + body parseado
      'json'    => $request->get_json_params(),  // JSON parseado (se houver)
      'body'    => method_exists($request,'get_body') ? $request->get_body() : null, // corpo bruto
    ];
    // anexa uma linha JSON por requisição
    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
  }
  return $result;
}, 10, 3);


add_filter('wp_check_filetype_and_ext', function($types, $file, $filename, $mimes){
  // Se WP não conseguiu determinar tipo/ext, detecta via conteúdo
  if (empty($types['ext']) || empty($types['type'])) {
    if (function_exists('finfo_open') && is_file($file)) {
      $f = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($f, $file);
      finfo_close($f);

      $map = [
        'image/jpeg' => ['ext' => 'jpg',  'type' => 'image/jpeg'],
        'image/png'  => ['ext' => 'png',  'type' => 'image/png'],
        'image/gif'  => ['ext' => 'gif',  'type' => 'image/gif'],
        'image/webp' => ['ext' => 'webp', 'type' => 'image/webp'],
        'image/avif' => ['ext' => 'avif', 'type' => 'image/avif'],
      ];
      if (isset($map[$mime])) {
        return [
          'ext'             => $map[$mime]['ext'],
          'type'            => $map[$mime]['type'],
          'proper_filename' => preg_match('/\.[a-z0-9]+$/i', $filename) ? $filename : ('image.'.$map[$mime]['ext']),
        ];
      }
    }
  }
  return $types;
}, 10, 4);
