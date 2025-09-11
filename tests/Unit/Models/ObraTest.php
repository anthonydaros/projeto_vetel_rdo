<?php
namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use Models\Obra;

class ObraTest extends TestCase
{
    private Obra $obra;
    
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../models/Obra.php';
        $this->obra = new Obra();
    }
    
    public function testCanCreateObraInstance(): void
    {
        $this->assertInstanceOf(Obra::class, $this->obra);
    }
    
    public function testCanSetAndGetProperties(): void
    {
        $this->obra->fk_id_contratante = 1;
        $this->obra->fk_id_contratada = 2;
        $this->obra->descricao_resumo = 'Test Construction Project';
        
        $this->assertEquals(1, $this->obra->fk_id_contratante);
        $this->assertEquals(2, $this->obra->fk_id_contratada);
        $this->assertEquals('Test Construction Project', $this->obra->descricao_resumo);
    }
    
    public function testPropertiesDefaultToNull(): void
    {
        $this->assertNull($this->obra->id_obra);
        $this->assertNull($this->obra->fk_id_contratante);
        $this->assertNull($this->obra->fk_id_contratada);
        $this->assertNull($this->obra->descricao_resumo);
    }
    
    public function testMagicGetterWorks(): void
    {
        $this->obra->descricao_resumo = 'Test Description';
        $value = $this->obra->__get('descricao_resumo');
        $this->assertEquals('Test Description', $value);
    }
    
    public function testMagicSetterWorks(): void
    {
        $this->obra->__set('descricao_resumo', 'New Description');
        $this->assertEquals('New Description', $this->obra->descricao_resumo);
    }
}