<?php

// Carrega o autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Carrega as classes do projeto
require_once __DIR__ . '/../api/core/Database.php';
require_once __DIR__ . '/../api/core/Response.php';
require_once __DIR__ . '/../api/core/Request.php';
require_once __DIR__ . '/../api/core/Router.php';
require_once __DIR__ . '/../api/core/Auth.php';
require_once __DIR__ . '/../api/model/User.php';
require_once __DIR__ . '/../api/model/AuthToken.php';
require_once __DIR__ . '/../api/model/Task.php';
require_once __DIR__ . '/../api/model/TaskCategory.php';
