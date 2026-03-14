<?php

class TaskCategory
{
    private static $database = null;

    public static function setDatabase($db)
    {
        self::$database = $db;
    }

    public static function getDatabase()
    {
        return self::$database ?: Database::getInstance()->getConnection();
    }

    public static function findAllByUserId($userId)
    {
        $db = self::getDatabase();
        $stmt = $db->prepare("SELECT * FROM todo_categories WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByIdAndUserId($id, $userId)
    {
        $db = self::getDatabase();
        $stmt = $db->prepare("SELECT * FROM todo_categories WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch();
    }

    public static function create($userId, $name, $color, $displayOrder, $icon = null)
    {
        $db = self::getDatabase();
        $stmt = $db->prepare("INSERT INTO todo_categories (user_id, name, color, display_order, icon, created_at) VALUES (:user_id, :name, :color, :display_order, :icon, NOW())");

        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'color' => $color,
            'display_order' => $displayOrder,
            'icon' => $icon,
        ]);

        if ($stmt->rowCount() > 0) {
            $lastId = $db->lastInsertId();
            return self::findByIdAndUserId($lastId, $userId);
        }
        return null;
    }

    public static function update($id, $name, $color, $displayOrder, $userId)
    {
        $db = self::getDatabase();
        $stmt = $db->prepare("UPDATE todo_categories SET name = :name, color = :color, display_order = :display_order, updated_at = NOW() WHERE id = :id");

        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'color' => $color,
            'display_order' => $displayOrder,
        ]);

        if ($stmt->rowCount() > 0) {
            return self::findByIdAndUserId($id, $userId);
        }
        return null;
    }

    public static function delete($id, $userId)
    {
        $db = self::getDatabase();
        $stmt = $db->prepare("DELETE FROM todo_categories WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

}