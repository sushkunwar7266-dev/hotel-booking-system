<?php
/**
 * Seed room types and random rooms with photos.
 * Run from the command line:  C:\xampp\php\php.exe seed\seed_rooms.php [number_of_rooms]
 * Safe to re-run: existing room types are reused and existing room numbers are skipped.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run from the command line only.'); }

$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../config/config.php';

$count = max(1, (int)($argv[1] ?? 20));
$src = __DIR__ . '/images/';
$root = dirname(__DIR__);
$webBase = '/' . basename($root) . '/uploads/';   // e.g. /hotel/uploads/

// Photos in seed/images: room_00.jpg ... room_15.jpg
$types = [
    ['name' => 'Standard Room', 'capacity' => 2, 'price' => [2500, 3500],
     'description' => 'A cosy, well-kept room with a comfortable double bed, work desk and everything you need for a short city stay.',
     'amenities' => ['Wi-Fi', 'AC', 'TV', 'Hot Shower', 'Work Desk'], 'photos' => [1, 15, 14]],
    ['name' => 'Deluxe King Room', 'capacity' => 2, 'price' => [4500, 6000],
     'description' => 'Spacious room with a king-size bed, elegant furnishings, city view and complimentary breakfast.',
     'amenities' => ['Wi-Fi', 'AC', 'Smart TV', 'Breakfast', 'Mini Bar', 'City View', 'Coffee Maker'], 'photos' => [0, 5, 9, 13]],
    ['name' => 'Twin Room', 'capacity' => 3, 'price' => [4000, 5000],
     'description' => 'Bright room with two single beds, ideal for friends or colleagues travelling together.',
     'amenities' => ['Wi-Fi', 'AC', 'TV', 'Breakfast', 'Wardrobe', 'Hot Shower'], 'photos' => [11, 15, 1]],
    ['name' => 'Executive Suite', 'capacity' => 3, 'price' => [8000, 11000],
     'description' => 'Luxury suite with a separate lounge and dining area, premium bedding, bathtub and large windows.',
     'amenities' => ['Wi-Fi', 'AC', 'Smart TV', 'Breakfast', 'Mini Bar', 'Bathtub', 'Lounge Area', 'Room Service'], 'photos' => [6, 10, 2, 3]],
    ['name' => 'Family Suite', 'capacity' => 5, 'price' => [9000, 12500],
     'description' => 'Two-room suite with a living area and extra beds, perfect for families staying together.',
     'amenities' => ['Wi-Fi', 'AC', 'TV', 'Breakfast', 'Living Room', 'Extra Bed', 'Kitchenette'], 'photos' => [7, 4, 3]],
    ['name' => 'Lake View Premium', 'capacity' => 2, 'price' => [12000, 16000],
     'description' => 'Premium room with a private balcony overlooking the lake and mountains, plus pool access.',
     'amenities' => ['Wi-Fi', 'AC', 'Smart TV', 'Breakfast', 'Balcony', 'Lake View', 'Pool Access', 'Mini Bar'], 'photos' => [12, 8, 2]],
];

function copy_photo(string $src, string $dir, string $name): string {
    global $root, $webBase;
    @mkdir("$root/uploads/$dir", 0755, true);
    copy($src, "$root/uploads/$dir/$name");
    return $webBase . "$dir/$name";
}

$pdo = db();
$typeIds = [];
foreach ($types as $t) {
    $s = $pdo->prepare("SELECT id FROM room_types WHERE name=?");
    $s->execute([$t['name']]);
    $id = $s->fetchColumn();
    if (!$id) {
        $slug = strtolower(preg_replace('/\W+/', '_', $t['name']));
        $img = copy_photo($src . sprintf('room_%02d.jpg', $t['photos'][0]), 'room_types', "seed_$slug.jpg");
        $pdo->prepare("INSERT INTO room_types (name, description, image, capacity, amenities, status) VALUES (?,?,?,?,?, 'active')")
            ->execute([$t['name'], $t['description'], $img, $t['capacity'], implode(', ', $t['amenities'])]);
        $id = $pdo->lastInsertId();
        echo "Created room type: {$t['name']}\n";
    }
    $typeIds[] = (int)$id;
}

$added = 0; $tries = 0;
while ($added < $count && $tries++ < $count * 20) {
    $ti = array_rand($types);
    $t = $types[$ti];
    $floor = [1 => 1, 0 => 2, 2 => 2, 3 => 4, 4 => 3, 5 => 5][$ti];   // pricier types on higher floors
    $number = (string)($floor * 100 + random_int(1, 30));

    $exists = $pdo->prepare("SELECT 1 FROM rooms WHERE room_number=?");
    $exists->execute([$number]);
    if ($exists->fetchColumn()) continue;

    $price = round(random_int($t['price'][0], $t['price'][1]) / 100) * 100;
    $photos = $t['photos'];
    shuffle($photos);
    $main = copy_photo($src . sprintf('room_%02d.jpg', $photos[0]), 'rooms', "seed_room_{$number}_main.jpg");
    $gallery = [];
    foreach (array_slice($photos, 1) as $k => $p) {
        $gallery[] = copy_photo($src . sprintf('room_%02d.jpg', $p), 'rooms', "seed_room_{$number}_g$k.jpg");
    }
    $amen = $t['amenities'];
    shuffle($amen);
    $amen = array_slice($amen, 0, random_int(4, count($amen)));
    $status = random_int(1, 10) === 1 ? 'maintenance' : 'available';
    $desc = $t['description'] . ' Room ' . $number . ' is on floor ' . $floor . '.';

    $pdo->prepare("INSERT INTO rooms (room_number, room_type_id, capacity, price, image, gallery, description, amenities, status) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$number, $typeIds[$ti], $t['capacity'], $price, $main, json_encode($gallery), $desc, implode(', ', $amen), $status]);
    echo "Added room $number ({$t['name']}) NPR $price [$status]\n";
    $added++;
}
echo "Done: $added room(s) added.\n";
