<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlaylistController extends Controller
{
    /**
     * Display a listing of playlists.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type');
            $limit = $request->get('limit', 10);

            $query = Playlist::active()->ordered();

            // Filtrar por tipo se especificado
            if ($type && in_array($type, ['dj', 'chart'])) {
                $query->where('type', $type);
            }

            // Carregar relacionamentos
            $query->withCount('tracks')
                  ->with('tracksWithProducts');

            $playlists = $query->limit($limit)->get();

            return response()->json([
                'status' => 'success',
                'data' => PlaylistResource::collection($playlists),
                'meta' => [
                    'total' => $playlists->count(),
                    'type' => $type ?? 'all'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar playlists:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }

    /**
     * Display the specified playlist.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $playlist = Playlist::active()
                ->withCount('tracks')
                ->with('tracksWithProducts')
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => new PlaylistResource($playlist)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Playlist não encontrada'
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar playlist:', [
                'playlist_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }

    /**
     * Get playlists by type (dj or chart).
     */
    public function byType(Request $request, string $type): JsonResponse
    {
        try {
            if (!in_array($type, ['dj', 'chart'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de playlist inválido. Use "dj" ou "chart".'
                ], 400);
            }

            $limit = $request->get('limit', 10);

            $playlists = Playlist::active()
                ->where('type', $type)
                ->ordered()
                ->withCount('tracks')
                ->with('tracksWithProducts')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => PlaylistResource::collection($playlists),
                'meta' => [
                    'total' => $playlists->count(),
                    'type' => $type
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar playlists por tipo:', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }

    /**
     * Get latest playlists for home page.
     */
    public function latest(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 6);

            $playlists = Playlist::active()
                ->ordered()
                ->withCount('tracks')
                ->with('tracksWithProducts')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => PlaylistResource::collection($playlists),
                'meta' => [
                    'total' => $playlists->count(),
                    'type' => 'latest'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar últimas playlists:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }
}
