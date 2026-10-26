<?php
declare(strict_types=1);

function validateProduct(array $input): array
{
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    $quantity = $input['quantity'] ?? '';
    $price = $input['price'] ?? '';
    if ($name === '' || strlen($name) > 200 || !preg_match('//u', $name)) {
        throw new InvalidArgumentException('Enter a product name of 1–200 bytes.');
    }
    if (!is_string($quantity) || !preg_match('/^\d{1,5}$/D', $quantity)) {
        throw new InvalidArgumentException('Quantity must be a whole number from 0 to 99999.');
    }
    if (!is_string($price) || !preg_match('/^\d{1,5}(?:\.\d{1,2})?$/D', $price)) {
        throw new InvalidArgumentException('Price must be from 0 to 99999.99, with up to two decimal places.');
    }
    $parts = explode('.', $price);
    $cents = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    return ['name' => $name, 'quantity' => (int)$quantity, 'price_cents' => $cents];
}

function inventory(string $directory, ?array $input = null): array
{
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('The storage directory must be writable by PHP.');
    }
    $lock = fopen($directory . '/products.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Could not lock inventory storage.');
    }
    try {
        $path = $directory . '/products.json';
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Could not read inventory.');
        $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || array_values($rows) !== $rows) throw new RuntimeException('Invalid inventory file.');
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'], $row['name'], $row['quantity'], $row['price_cents'], $row['submitted_at'], $row['version']) || !is_int($row['quantity']) || !is_int($row['price_cents'])) {
                throw new RuntimeException('Invalid inventory record.');
            }
        }
        if ($input !== null) {
            $product = validateProduct($input);
            $id = $input['id'] ?? '';
            if (!is_string($id)) throw new InvalidArgumentException('Invalid product ID.');
            if ($id === '') {
                if (count($rows) >= 1000) throw new InvalidArgumentException('This file-based inventory supports up to 1000 products.');
                $rows[] = ['id' => bin2hex(random_bytes(12))] + $product + [
                    'submitted_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
                    'version' => 1,
                ];
            } else {
                $found = false;
                foreach ($rows as &$row) {
                    if ($row['id'] !== $id) continue;
                    if (($input['version'] ?? null) !== $row['version']) {
                        throw new DomainException('This row changed in another window. Refresh the list and edit it again.');
                    }
                    $row = array_replace($row, $product, ['version' => $row['version'] + 1]);
                    $found = true;
                    break;
                }
                unset($row);
                if (!$found) throw new InvalidArgumentException('Product not found.');
            }
            $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            $temp = tempnam($directory, 'inventory-');
            if ($temp === false) throw new RuntimeException('Could not create temporary storage.');
            try {
                if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $path)) {
                    throw new RuntimeException('Could not save inventory.');
                }
            } finally {
                if (is_file($temp)) unlink($temp);
            }
        }
        usort($rows, fn(array $a, array $b): int => strcmp($a['submitted_at'], $b['submitted_at']));
        $total = 0;
        foreach ($rows as &$row) {
            $row['total_cents'] = $row['quantity'] * $row['price_cents'];
            $total += $row['total_cents'];
        }
        unset($row);
        return ['products' => $rows, 'grand_total_cents' => $total];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
