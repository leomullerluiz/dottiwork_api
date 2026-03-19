<?php
class Task
{
    private static $database;

    public static function setDatabase($db)
    {
        self::$database = $db;
    }
    public static function findAllByUserId($userId)
    {
        $db = self::$database ?: Database::getInstance()->getConnection();
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

    public static function delete($taskId, $userId)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM todo_lists WHERE id = :task_id AND user_id = :user_id");
        $stmt->execute(params: ['task_id' => $taskId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function filter($userId, $filters)
    {
        $db = Database::getInstance()->getConnection();

        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $conditions[] = 'category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['priority']) && $filters['priority'] !== '') {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (isset($filters['is_completed']) && $filters['is_completed'] !== '') {
            $conditions[] = 'is_completed = :is_completed';
            $params['is_completed'] = (int) $filters['is_completed'];
        }

        if (isset($filters['due_date']) && $filters['due_date'] !== '') {
            $conditions[] = 'due_date <= :due_date';
            $params['due_date'] = $filters['due_date'];
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $conditions[] = '(title LIKE :search )';
            //todo: verificar o porque o filtro nao busca com 'description'
            //SQLSTATE[HY093]: Invalid parameter number
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = 'SELECT * FROM todo_lists WHERE ' . implode(' AND ', $conditions);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

}