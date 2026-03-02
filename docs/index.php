<?php
$user = 'admin';
$pass = 'admin';

if (
  !isset($_SERVER['PHP_AUTH_USER'])
  || $_SERVER['PHP_AUTH_USER'] !== $user
  || $_SERVER['PHP_AUTH_PW'] !== $pass
) {
  header('WWW-Authenticate: Basic realm="Restrito"');
  header('HTTP/1.0 401 Unauthorized');
  echo "Acesso negado.";
  exit;
}
?>
<!-- HTML for static distribution bundle build -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Swagger UI</title>
  <link rel="stylesheet" type="text/css" href="./swagger-ui.css" />
  <link rel="stylesheet" type="text/css" href="index.css" />
  <link rel="icon" type="image/png" href="./favicon-32x32.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="./favicon-16x16.png" sizes="16x16" />
</head>

<body>
  <div id="swagger-ui"></div>
  <script src="./swagger-ui-bundle.js" charset="UTF-8"> </script>
  <script src="./swagger-ui-standalone-preset.js" charset="UTF-8"> </script>
  <script src="./swagger-initializer.js" charset="UTF-8"> </script>
</body>

</html>