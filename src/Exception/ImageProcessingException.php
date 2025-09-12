<?php
declare(strict_types=1);

namespace Src\Exception;

/**
 * Exceção para erros de processamento de imagens
 */
class ImageProcessingException extends ServiceException
{
    protected string $userMessage = 'Erro no processamento da imagem';
    
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        
        // Define mensagens específicas baseadas no erro
        if (str_contains($message, 'driver')) {
            $this->userMessage = 'Sistema de processamento de imagens não disponível';
        } elseif (str_contains($message, 'formato')) {
            $this->userMessage = 'Formato de imagem não suportado';
        } elseif (str_contains($message, 'tamanho')) {
            $this->userMessage = 'Não foi possível otimizar o tamanho da imagem';
        }
    }
}