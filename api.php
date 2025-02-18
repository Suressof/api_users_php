<?php

// Файл для хранения данных пользователей
$usersFile = 'users.json';

// Проверка существования файла и инициализация пустого массива, если файл не существует
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}

// Получение запроса и тела запроса
$requestMethod = $_SERVER['REQUEST_METHOD'];
$inputData = json_decode(file_get_contents('php://input'), true);

// Чтение текущих данных из файла
function getUsers() {
    global $usersFile;
    return json_decode(file_get_contents($usersFile), true) ?? [];
}

// Сохранение данных в файл
function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Валидация данных пользователя
function validateUser($user) {
    if (empty($user['username']) || empty($user['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }
}

// Обработка запросов
switch ($requestMethod) {
    case 'POST': // Создание пользователя
        validateUser($inputData);

        $users = getUsers();
        $existingUser = array_filter($users, function ($user) use ($inputData) {
            return $user['username'] === $inputData['username'];
        });

        if (!empty($existingUser)) {
            http_response_code(409); // Конфликт
            echo json_encode(['error' => 'User already exists']);
            exit;
        }

        $newUser = [
            'id' => uniqid(),
            'username' => $inputData['username'],
            'password' => password_hash($inputData['password'], PASSWORD_BCRYPT),
            'email' => $inputData['email'] ?? null,
        ];

        $users[] = $newUser;
        saveUsers($users);

        http_response_code(201); // Создано
        echo json_encode($newUser);
        break;

    case 'PUT': // Обновление информации пользователя
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $userId = $_GET['id'];
        $users = getUsers();

        $userIndex = array_search($userId, array_column($users, 'id'));
        if ($userIndex === false) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        validateUser($inputData);

        $users[$userIndex]['username'] = $inputData['username'];
        $users[$userIndex]['password'] = password_hash($inputData['password'], PASSWORD_BCRYPT);
        $users[$userIndex]['email'] = $inputData['email'] ?? $users[$userIndex]['email'];

        saveUsers($users);

        http_response_code(200);
        echo json_encode($users[$userIndex]);
        break;

    case 'DELETE': // Удаление пользователя
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $userId = $_GET['id'];
        $users = getUsers();

        $userIndex = array_search($userId, array_column($users, 'id'));
        if ($userIndex === false) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        unset($users[$userIndex]);
        saveUsers(array_values($users));

        http_response_code(200);
        echo json_encode(['message' => 'User deleted']);
        break;

    case 'GET': // Авторизация или получение информации о пользователе
        if (isset($_GET['username']) && isset($_GET['password'])) {
            // Авторизация
            $users = getUsers();
            $user = array_filter($users, function ($user) {
                return $user['username'] === $_GET['username'];
            });

            if (empty($user)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                exit;
            }

            $user = array_shift($user);
            if (!password_verify($_GET['password'], $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                exit;
            }

            http_response_code(200);
            echo json_encode(['message' => 'Authorized', 'user' => $user]);
        } elseif (isset($_GET['id'])) {
            // Получение информации о пользователе
            $userId = $_GET['id'];
            $users = getUsers();

            $user = array_filter($users, function ($user) use ($userId) {
                return $user['id'] === $userId;
            });

            if (empty($user)) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit;
            }

            http_response_code(200);
            echo json_encode(array_shift($user));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
        }
        break;

    default:
        http_response_code(405); // Метод не поддерживается
        echo json_encode(['error' => 'Method not allowed']);
}