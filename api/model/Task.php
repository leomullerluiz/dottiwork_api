<?php
class Task
{
    public static function findAllByUserId($userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_lists WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByCategorieIdAndUserId($idCategorieId, $userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_lists WHERE category_id = :category_id AND user_id = :user_id");
        $stmt->execute(['category_id' => $idCategorieId, 'user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findByIdAndUserId($id, $userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM todo_lists WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch();
    }

    public static function create($userId, $categoryId, $title, $description, $isCompleted, $priority, $displayOrder, $dueDate)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO todo_lists (user_id, category_id, title, description, is_completed, priority, display_order, due_date, created_at) VALUES (:user_id, :category_id, :title, :description, :is_completed, :priority, :display_order, :due_date, NOW())");

        $stmt->execute([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description,
            'is_completed' => $isCompleted,
            'priority' => $priority,
            'display_order' => $displayOrder,
            'due_date' => $dueDate,
        ]);

        if ($stmt->rowCount() > 0) {
            $lastId = $db->lastInsertId();
            return self::findByIdAndUserId($lastId, $userId);
        }
        return null;
    }

    public static function update($taskId, $userId, $categoryId, $title, $description, $isCompleted, $priority, $displayOrder, $dueDate)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE todo_lists SET category_id = :category_id, title = :title, description = :description, is_completed = :is_completed, priority = :priority, display_order = :display_order, due_date = :due_date, updated_at = NOW() WHERE id = :id AND user_id = :user_id");

        $stmt->execute([
            'id' => $taskId,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description,
            'is_completed' => $isCompleted,
            'priority' => $priority,
            'display_order' => $displayOrder,
            'due_date' => $dueDate,
        ]);

        if ($stmt->rowCount() > 0) {
            return self::findByIdAndUserId($taskId, $userId);
        }
        return null;
    }

    //todo: delete byId

}