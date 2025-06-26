<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    /**
     * Listar endereços do usuário
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $addresses = Address::forUser($user->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $addresses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar endereços'
            ], 500);
        }
    }

    /**
     * Criar novo endereço
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'street' => 'required|string|max:255',
                'number' => 'required|string|max:20',
                'complement' => 'nullable|string|max:100',
                'neighborhood' => 'required|string|max:100',
                'city' => 'required|string|max:100',
                'state' => 'required|string|size:2',
                'zip_code' => 'required|string|max:9',
                'is_default' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['user_id'] = $user->id;

            // Se é o primeiro endereço ou foi marcado como padrão
            if ($request->is_default || $user->addresses()->count() === 0) {
                // Remove o padrão dos outros endereços
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            $address = Address::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Endereço criado com sucesso!',
                'data' => $address
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar endereço'
            ], 500);
        }
    }

    /**
     * Atualizar endereço
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();

            $address = Address::forUser($user->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'street' => 'sometimes|required|string|max:255',
                'number' => 'sometimes|required|string|max:20',
                'complement' => 'sometimes|nullable|string|max:100',
                'neighborhood' => 'sometimes|required|string|max:100',
                'city' => 'sometimes|required|string|max:100',
                'state' => 'sometimes|required|string|size:2',
                'zip_code' => 'sometimes|required|string|max:9',
                'is_default' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Se foi marcado como padrão
            if (isset($data['is_default']) && $data['is_default']) {
                // Remove o padrão dos outros endereços
                $user->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
            }

            $address->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Endereço atualizado com sucesso!',
                'data' => $address
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar endereço'
            ], 500);
        }
    }

    /**
     * Deletar endereço
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            $address = Address::forUser($user->id)->findOrFail($id);

            // Se é o endereço padrão e há outros endereços, define outro como padrão
            if ($address->is_default) {
                $nextAddress = $user->addresses()->where('id', '!=', $id)->first();
                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }

            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Endereço removido com sucesso!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover endereço'
            ], 500);
        }
    }

    /**
     * Definir endereço como padrão
     */
    public function setDefault(Request $request, $id)
    {
        try {
            $user = $request->user();

            $address = Address::forUser($user->id)->findOrFail($id);

            // Remove o padrão dos outros endereços
            $user->addresses()->update(['is_default' => false]);

            // Define este como padrão
            $address->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Endereço definido como padrão!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao definir endereço padrão'
            ], 500);
        }
    }
}
