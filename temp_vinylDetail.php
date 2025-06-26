/**
 * Exibir detalhes de um disco de vinil específico
 */
public function vinylDetail($slug)
{
    $product = Product::where('slug', $slug)
        ->where('productable_type', 'App\\Models\\VinylMaster')
        ->with([
            'productable.tracks' => function($query) {
                $query->orderBy('position', 'asc');
            },
            'productable.recordLabel',
            'productable.artists',
            'productable.vinylSec',
            'productable.vinylSec.weight',
            'productable.vinylSec.dimension',
            'productable.vinylSec.midiaStatus',
            'productable.vinylSec.coverStatus',
            'productable.categories',
            'productable.media'
        ])
        ->firstOrFail();
        
    return response()->json([
        'status' => 'success',
        'data' => $product
    ]);
}
