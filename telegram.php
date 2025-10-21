<?php

// ===== ВАШИ ДАННЫЕ (ОБЯЗАТЕЛЬНО ЗАПОЛНИТЬ!) =====
// Токен, который вы получили от @BotFather
$botToken = '';
// ID чата, куда будут приходить сообщения
$chatId = '';
// Длительность ремонта в часах (для блокировки времени)
$repairDurationHours = 2;
// ===============================================

$bookingsFile = 'bookings.json';

// Устанавливаем заголовок, чтобы браузер понимал, что ответ в формате JSON
header('Content-Type: application/json');

// Проверяем, что запрос был методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Получаем данные из формы
$name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
$phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$date = isset($_POST['date']) ? trim(strip_tags($_POST['date'])) : '';
$time = isset($_POST['time']) ? trim(strip_tags($_POST['time'])) : '';
$brand = isset($_POST['brand']) ? trim(strip_tags($_POST['brand'])) : 'Не указана';
$model = isset($_POST['model']) && !empty($_POST['model']) ? trim(strip_tags($_POST['model'])) : 'Не указана';
$fault = isset($_POST['fault']) ? trim(strip_tags($_POST['fault'])) : 'Не указана';
$isUrgent = isset($_POST['urgent']); // Проверяем, отмечен ли чекбокс

// Валидация полей
if (empty($name) || empty($phone) || empty($date) || empty($time) || empty($fault)) {
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, заполните все обязательные поля.']);
    exit;
}

// --- Проверка бронирования на длительность ремонта ---
$bookings = json_decode(file_get_contents($bookingsFile), true);
if ($bookings === null) {
    $bookings = [];
}

$selectedHour = (int)explode(':', $time)[0];

for ($i = 0; $i < $repairDurationHours; $i++) {
    $hourToCheck = $selectedHour + $i;
    $timeToCheck = $hourToCheck . ':00';

    foreach ($bookings as $booking) {
        if ($booking['date'] === $date && $booking['time'] === $timeToCheck) {
            echo json_encode(['success' => false, 'message' => 'К сожалению, это время и последующие часы уже заняты. Пожалуйста, выберите другое время.']);
            exit;
        }
    }
}
// --- Конец проверки ---


// Составляем сообщение для Telegram
$message = '';
if ($isUrgent) {
    $message .= "<b>‼️ СРОЧНЫЙ ВЫЗОВ ‼️</b>\n\n";
}

$message .= "<b>🗓️ Новая запись на ремонт! 🗓️</b>\n\n";
$message .= "<b>Имя:</b> " . htmlspecialchars($name) . "\n";
$message .= "<b>Телефон:</b> " . htmlspecialchars($phone) . "\n\n";
$message .= "<b>Марка:</b> " . htmlspecialchars($brand) . "\n";
$message .= "<b>Модель:</b> " . htmlspecialchars($model) . "\n";
$message .= "<b>Неисправность:</b> " . htmlspecialchars($fault) . "\n\n";
$message .= "<b>Желаемая дата:</b> " . htmlspecialchars($date) . "\n";
$message .= "<b>Желаемое время:</b> " . htmlspecialchars($time);


// Формируем URL для отправки запроса к Telegram API
$url = "https://api.telegram.org/bot{$botToken}/sendMessage";

// Параметры запроса
$postFields = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'HTML'
];

// Отправляем запрос с помощью cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Проверяем ответ от Telegram
if ($httpCode == 200) {
    // Если отправка успешна, сохраняем все забронированные часы
    for ($i = 0; $i < $repairDurationHours; $i++) {
        $hourToBook = $selectedHour + $i;
        // Не бронируем часы за пределами рабочего дня (например, после 18:00)
        if ($hourToBook <= 18) {
            $timeToBook = $hourToBook . ':00';
            $newBooking = ['date' => $date, 'time' => $timeToBook];
            $bookings[] = $newBooking;
        }
    }
    
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
} else {
    // Если Telegram вернул ошибку, показываем ее для диагностики
    echo json_encode(['success' => false, 'message' => 'Ошибка Telegram: ' . $response]);
}

?>