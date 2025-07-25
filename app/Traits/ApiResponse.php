<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Resposta de sucesso padronizada
     */
    protected function successResponse($data = null, string $message = '', array $meta = [], int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Resposta de erro padronizada
     */
    protected function errorResponse(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Resposta de validação com erros
     */
    protected function validationErrorResponse(array $errors, string $message = 'Dados inválidos'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Resposta de não encontrado
     */
    protected function notFoundResponse(string $message = 'Recurso não encontrado'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Resposta de não autorizado
     */
    protected function unauthorizedResponse(string $message = 'Não autorizado'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Resposta de servidor interno
     */
    protected function serverErrorResponse(string $message = 'Erro interno do servidor'): JsonResponse
    {
        return $this->errorResponse($message, 500);
    }

    /**
     * Resposta de criação bem-sucedida
     */
    protected function createdResponse($data = null, string $message = 'Criado com sucesso'): JsonResponse
    {
        return $this->successResponse($data, $message, [], 201);
    }

    /**
     * Resposta de atualização bem-sucedida
     */
    protected function updatedResponse($data = null, string $message = 'Atualizado com sucesso'): JsonResponse
    {
        return $this->successResponse($data, $message);
    }

    /**
     * Resposta de exclusão bem-sucedida
     */
    protected function deletedResponse(string $message = 'Excluído com sucesso'): JsonResponse
    {
        return $this->successResponse(null, $message);
    }
}