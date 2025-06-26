<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VinylMaster;
use Illuminate\Http\Request;

class VinylMasterController extends Controller
{
    /**
     * Listar todos os discos de vinil com paginação
     */
    public function index()
    {
        $vinylMasters = VinylMaster::with(['product', 'vinylSections', 'recordLabel'])
            ->paginate(15);
        
        return response()->json([
            'status' => 'success',
            'data' => $vinylMasters
        ]);
    }

    /**
     * Exibir detalhes de um disco específico pelo slug
     */
    public function show($slug)
    {
        $vinylMaster = VinylMaster::where('slug', $slug)
            ->with([
                'product', 
                'vinylSections' => function($query) {
                    $query->orderBy('side', 'asc')
                          ->orderBy('track_number', 'asc');
                }, 
                'recordLabel'
            ])
            ->firstOrFail();
        
        return response()->json([
            'status' => 'success',
            'data' => $vinylMaster
        ]);
    }

    /**
     * Buscar discos por termo de pesquisa
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        $vinylMasters = VinylMaster::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->with(['product', 'recordLabel'])
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vinylMasters
        ]);
    }

    /**
     * Filtrar discos por ano de lançamento
     */
    public function filterByYear($year)
    {
        $vinylMasters = VinylMaster::where('release_year', $year)
            ->with(['product', 'recordLabel'])
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vinylMasters
        ]);
    }

    /**
     * Filtrar discos por gravadora
     */
    public function filterByLabel($labelId)
    {
        $vinylMasters = VinylMaster::where('record_label_id', $labelId)
            ->with(['product', 'recordLabel'])
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vinylMasters
        ]);
    }
}
