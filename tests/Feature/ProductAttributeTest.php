<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\StoreType;
use App\Models\User;
use App\Services\MissingDataDetector;
use App\Services\ProductAttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAttributeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected StoreType $clothingType;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create clothing store type
        $this->clothingType = StoreType::create([
            'name' => 'clothing',
            'display_name' => 'متجر ملابس',
            'display_name_en' => 'Clothing Store',
            'required_attributes' => ['size', 'color'],
            'optional_attributes' => ['material'],
        ]);
        
        // Create user with clothing store
        $this->user = User::factory()->create([
            'store_type_id' => $this->clothingType->id,
        ]);
        
        // Create category
        $this->category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'ملابس رجالية',
            'is_active' => true,
        ]);
        
        // Create product
        $this->product = Product::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'قميص رجالي',
            'description' => 'قميص قطني عالي الجودة',
            'price' => 25000,
            'is_available' => true,
        ]);
        
        // Add size attributes
        foreach (['S', 'M', 'L', 'XL'] as $size) {
            ProductAttribute::create([
                'product_id' => $this->product->id,
                'attribute_key' => 'size',
                'attribute_value' => $size,
                'stock_quantity' => 10,
                'is_available' => true,
            ]);
        }
        
        // Add color attributes
        foreach (['black', 'white', 'red'] as $color) {
            ProductAttribute::create([
                'product_id' => $this->product->id,
                'attribute_key' => 'color',
                'attribute_value' => $color,
                'stock_quantity' => 10,
                'is_available' => true,
            ]);
        }
    }

    public function test_product_attribute_service_extracts_size_from_message(): void
    {
        $service = new ProductAttributeService();
        
        $message = 'اريد مقاس L';
        $attributes = $service->extractAttributesFromMessage($message);
        
        $this->assertArrayHasKey('size', $attributes);
        $this->assertEquals('L', $attributes['size']);
    }

    public function test_product_attribute_service_extracts_color_from_message(): void
    {
        $service = new ProductAttributeService();
        
        $message = 'اريد اللون الاسود';
        $attributes = $service->extractAttributesFromMessage($message);
        
        $this->assertArrayHasKey('color', $attributes);
        $this->assertEquals('black', $attributes['color']);
    }

    public function test_product_attribute_service_extracts_multiple_attributes(): void
    {
        $service = new ProductAttributeService();
        
        $message = 'اريد مقاس M لون احمر';
        $attributes = $service->extractAttributesFromMessage($message);
        
        $this->assertArrayHasKey('size', $attributes);
        $this->assertArrayHasKey('color', $attributes);
        $this->assertEquals('M', $attributes['size']);
        $this->assertEquals('red', $attributes['color']);
    }

    public function test_missing_data_detector_identifies_missing_size(): void
    {
        $detector = new MissingDataDetector($this->user);
        
        // Test getMissingProductAttributes directly
        $missing = $detector->getMissingProductAttributes(
            $this->product,
            ['color' => 'black'] // Only color provided, missing size
        );
        
        $this->assertContains('size', $missing);
    }

    public function test_missing_data_detector_identifies_missing_color(): void
    {
        $detector = new MissingDataDetector($this->user);
        
        // Test getMissingProductAttributes directly
        $missing = $detector->getMissingProductAttributes(
            $this->product,
            ['size' => 'L'] // Only size provided, missing color
        );
        
        $this->assertContains('color', $missing);
    }

    public function test_missing_data_detector_returns_no_missing_when_all_provided(): void
    {
        $detector = new MissingDataDetector($this->user);
        
        // Test getMissingProductAttributes directly
        $missing = $detector->getMissingProductAttributes(
            $this->product,
            [
                'size' => 'L',
                'color' => 'black',
            ]
        );
        
        $this->assertEmpty($missing);
    }

    public function test_product_attribute_service_checks_availability(): void
    {
        $service = new ProductAttributeService();
        
        $availability = $service->checkAvailability($this->product, [
            'size' => 'L',
            'color' => 'black',
        ]);
        
        $this->assertTrue($availability['available']);
    }

    public function test_product_attribute_service_handles_unavailable_size(): void
    {
        // Set S size as unavailable
        ProductAttribute::where('product_id', $this->product->id)
            ->where('attribute_key', 'size')
            ->where('attribute_value', 'S')
            ->update(['is_available' => false]);
        
        $service = new ProductAttributeService();
        
        $availability = $service->checkAvailability($this->product, [
            'size' => 'S',
            'color' => 'black',
        ]);
        
        $this->assertFalse($availability['available']);
        $this->assertNotEmpty($availability['messages']);
        $this->assertStringContainsString('مقاس', $availability['messages'][0]);
    }

    public function test_store_type_requires_size_attribute(): void
    {
        $this->assertTrue($this->clothingType->requiresAttribute('size'));
        $this->assertTrue($this->clothingType->requiresAttribute('color'));
        $this->assertFalse($this->clothingType->requiresAttribute('datetime'));
    }

    public function test_product_has_attributes_relationship(): void
    {
        $this->assertEquals(7, $this->product->attributes()->count()); // 4 sizes + 3 colors
    }

    public function test_missing_data_detector_builds_question_for_missing_attributes(): void
    {
        $detector = new MissingDataDetector($this->user);
        
        $missingData = [
            'attributes' => [
                0 => [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'missing' => ['size'],
                    'available_options' => [
                        'size' => [
                            'name' => 'المقاس',
                            'values' => ['S', 'M', 'L', 'XL'],
                            'display_values' => ['S', 'M', 'L', 'XL'],
                        ],
                    ],
                ],
            ],
            'has_missing' => true,
        ];
        
        $question = $detector->buildMissingAttributeQuestion($missingData);
        
        $this->assertNotNull($question);
        $this->assertStringContainsString('مقاس', $question); // Should ask about size
    }
    
    public function test_general_store_type_does_not_require_attributes(): void
    {
        // Create a general store type without required attributes
        $generalType = StoreType::create([
            'name' => 'general',
            'display_name' => 'متجر عام',
            'display_name_en' => 'General Store',
            'required_attributes' => [],
            'optional_attributes' => [],
        ]);
        
        // Create user with general store
        $generalUser = User::factory()->create([
            'store_type_id' => $generalType->id,
        ]);
        
        $detector = new MissingDataDetector($generalUser);
        
        // Should return empty even without any attributes provided
        $missing = $detector->getMissingProductAttributes($this->product, []);
        
        $this->assertEmpty($missing);
    }
}
