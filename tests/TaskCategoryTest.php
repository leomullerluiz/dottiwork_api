<?php

use PHPUnit\Framework\TestCase;

class TaskCategoryTest extends TestCase
{
    private $mockDb;
    private $mockStmt;

    protected function setUp(): void
    {
        // Mock do PDOStatement
        $this->mockStmt = $this->createMock(PDOStatement::class);

        // Mock do PDO
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockDb->method('prepare')->willReturn($this->mockStmt);

        // Injeta o mock na classe real
        \TaskCategory::setDatabase($this->mockDb);
    }

    public function testFindAllByUserId()
    {
        $userId = 123;

        // Dados esperados (agora correspondendo ao user_id)
        $expectedCategories = [
            ['id' => 1, 'user_id' => $userId, 'name' => 'Trabalho', 'color' => '#FF0000'],
            ['id' => 2, 'user_id' => $userId, 'name' => 'Pessoal', 'color' => '#00FF00'],
        ];

        // Configura o mock para validar que execute foi chamado com os dados corretos
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => $userId])  // ← Valida o argumento
            ->willReturn(true);

        $this->mockStmt->method('fetchAll')->willReturn($expectedCategories);

        // Executa o método com o user_id correto
        $result = \TaskCategory::findAllByUserId(123);

        // Assertions - VERIFICA SE O RESULTADO É CORRETO
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Trabalho', $result[0]['name']);
        $this->assertEquals('#FF0000', $result[0]['color']);
        $this->assertEquals('Pessoal', $result[1]['name']);
        // Valida que os resultados têm o user_id correto
        $this->assertEquals($userId, $result[0]['user_id']);
        $this->assertEquals($userId, $result[1]['user_id']);
    }


}
