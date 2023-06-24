<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$app->get('/users', function ($request, $response) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);

    $term = $request->getQueryParam('term') ?? '';
    $filteredUsers = array_filter($users, fn($user) => str_contains($user['nickname'], $term));

    $messages = $this->get('flash')->getMessages();
    $params = [
        'term' => $term,
        'users' => $filteredUsers,
        'flash' => $messages,
        'currentUser' => $_SESSION['userEmail'] ?? null
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$router = $app->getRouteCollector()->getRouteParser();

$app->post('/users', function ($request, $response) use ($router) {
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);

    $userParams = $request->getParsedBodyParam('user');
    $validator = new App\Validator();
    $errors = $validator->validate($userParams);
    if (count($errors) === 0) {
        $userID = ['id' => uniqid()];
        $newUser = array_merge($userID, $userParams);

        $users[] = $newUser;
        $allUsersJson = json_encode($users);

        $this->get('flash')->addMessage('success', 'User was added successfully');
        return $response->withHeader('Set-Cookie', "users={$allUsersJson}")->withRedirect($router->urlFor('users'), 302);
    }
    $params = [
        'user' => $userParams,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
});

$app->get('/users/new', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = [
        'user' => [
            'nickname' => '',
            'email' => ''
        ],
        'errors' => [],
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users.new');

$app->get('/users/login', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = [
        'currentUser' => $_SESSION['userEmail'] ?? null,
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, "users/login.phtml", $params);
})->setName('login');

$app->post('/users/session', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    if ($user['email'] === '') {
        $this->get('flash')->addMessage('errors', 'Can\'t be blank');
        return $response->withStatus(422)->withRedirect($router->urlFor('login')); 
    }
    $_SESSION['userEmail'] = $user['email'];
    return $response->withRedirect($router->urlFor('users')); 
});

$app->delete('/users/session', function ($request, $response) use ($router) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect($router->urlFor('users'));
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $filteredArray = array_filter($users, function ($user) use ($id) {
        return $user['id'] === $id;
    });
    if (empty($filteredArray)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = array_merge($filteredArray);
    $params = [
        'id' => $user['id'],
        'nickname' => $user['nickname']
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $filteredArray = array_filter($users, function ($user) use ($id) {
        return $user['id'] === $id;
    });
    if (empty($filteredArray)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = array_merge($filteredArray);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'id' => $id,
        'user' => $user,
        'errors' => [],
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, "users/edit.phtml", $params);
})->setName('usersEdit');

$app->patch('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $filteredArray = array_filter($users, function ($user) use ($id) {
        return $user['id'] === $id;
    });
    if (empty($filteredArray)) {
        return $response->write('Page not found')->withStatus(404);
    }
    [$user] = array_merge($filteredArray);

    $data = $request->getParsedBodyParam('user');
    $validator = new App\Validator();
    $errors = $validator->validate($data);
    if (count($errors) === 0) {
        $this->get('flash')->addMessage('success', 'User has been updated');
        $contentUpdated = array_map(function ($user) use ($id, $data) {
            if ($id === $user['id']) {
                $user['nickname'] = $data['nickname'];
                $user['email'] = $data['email'];
            }
            return $user;
        }, $users);
        $allUsersJson = json_encode($users);
        return $response->withHeader('Set-Cookie', "users={$allUsersJson}")->withRedirect($router->urlFor('usersEdit', ['id' => $id]));
    }

    $params = [
        'id' => $id,
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response->withStatus(422), "users/edit.phtml", $params);
});

$app->delete('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $filteredArray = array_filter($users, function ($user) use ($id) {
        return $user['id'] !== $id;
    });
    $allUsersJson = json_encode($filteredArray);

    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withHeader('Set-Cookie', "users={$allUsersJson}")->withRedirect($router->urlFor('users'), 302);
});

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');

$app->run();