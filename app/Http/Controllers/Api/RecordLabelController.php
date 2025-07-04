<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecordLabel;
use App\Models\Product;
use Illuminate\Http\Request;

class RecordLabelController extends Controller
{
    /**
     * Listar todas as gravadoras
     */
    public function index()
    {
        $labels = RecordLabel::withCount('vinylMasters')
            ->has('vinylMasters')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $labels
        ]);
    }

    /**
     * Mostrar detalhes de uma gravadora específica
     */
    public function show($slug)
    {
        $label = RecordLabel::where('slug', $slug)
            ->withCount('vinylMasters')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $label
        ]);
    }

    /**
     * Listar produtos de uma gravadora específica
     */
    public function productsByLabel($slug)
    {
        $label = RecordLabel::where('slug', $slug)->firstOrFail();

        // Buscar produtos que têm vinyl_masters relacionados a esta gravadora
        $products = Product::whereHasMorph(
                'productable',
                ['App\\Models\\VinylMaster'],
                function ($query) use ($label) {
                    $query->where('record_label_id', $label->id);
                }
            )
            ->with([
                'productable.recordLabel',
                'productable.artists',
                'productable.vinylSec',
                'productable.categories',
                'productable.media',
                'productable.tracks'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'label' => $label->name,
            'data' => $products
        ]);
    }
}
