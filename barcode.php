<?php
/**
 * Atliere Barcode System - Backend Implementation
 * This class handles barcode generation, validation, and database operations
 */

class AtliereBarcode {
    private $db;
    
    // Category codes mapping
    private $categoryMap = [
        'T-Shirts' => 'TS',
        'Hoodies' => 'HD',
        'Jackets' => 'JK',
        'Pants' => 'PN',
        'Shoes' => 'SH',
        'Accessories' => 'AC'
    ];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate barcode for a product-size combination
     * Format: ATL-CCPPPP-SS-CKK
     */
    public function generateBarcode($categoryCode, $productId, $sizeCode) {
        // Format product ID to 4 digits
        $productCode = str_pad($productId, 4, '0', STR_PAD_LEFT);
        
        // Ensure size code is 2 characters
        $sizeCode = str_pad(substr($sizeCode, 0, 3), 2, '0', STR_PAD_RIGHT);
        
        // Create base barcode
        $baseCode = "ATL-{$categoryCode}{$productCode}-{$sizeCode}";
        
        // Calculate check digits
        $checkSum = $productId + ord($sizeCode[0]) + ord($sizeCode[1]);
        $checkDigits = str_pad($checkSum % 100, 2, '0', STR_PAD_LEFT);
        
        // Complete barcode
        return "{$baseCode}-{$checkDigits}";
    }
    
    /**
     * Validate barcode format
     */
    public function validateBarcode($barcode) {
        // Check format: ATL-CCPPPP-SS-CKK
        $pattern = '/^ATL-[A-Z]{2}\d{4}-[A-Z0-9]{2}-\d{2}$/';
        return preg_match($pattern, $barcode) === 1;
    }
    
    /**
     * Parse barcode to extract components
     */
    public function parseBarcode($barcode) {
        if (!$this->validateBarcode($barcode)) {
            return false;
        }
        
        $parts = explode('-', $barcode);
        
        return [
            'prefix' => $parts[0],
            'category_code' => substr($parts[1], 0, 2),
            'product_id' => (int)substr($parts[1], 2, 4),
            'size_code' => $parts[2],
            'check_digits' => $parts[3]
        ];
    }
    
    /**
     * Get category code from category name
     */
    public function getCategoryCode($categoryName) {
        return $this->categoryMap[$categoryName] ?? 'XX';
    }
    
    /**
     * Add barcode to product_sizes table
     */
    public function addBarcodeToProductSize($productSizeId, $barcode) {
        $stmt = $this->db->prepare("
            UPDATE product_sizes 
            SET barcode = ? 
            WHERE product_size_id = ?
        ");
        
        return $stmt->execute([$barcode, $productSizeId]);
    }
    
    /**
     * Generate and save barcode for a product size
     */
    public function generateAndSaveBarcode($productSizeId) {
        // Get product details
        $stmt = $this->db->prepare("
            SELECT 
                ps.product_size_id,
                ps.product_id,
                p.category_id,
                c.category_name,
                CASE 
                    WHEN ps.clothing_size_id IS NOT NULL THEN cs.size_name
                    WHEN ps.shoe_size_id IS NOT NULL THEN CAST(ss.size_us AS CHAR)
                    ELSE 'OS'
                END AS size_name
            FROM product_sizes ps
            JOIN products p ON ps.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
            LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
            WHERE ps.product_size_id = ?
        ");
        
        $stmt->execute([$productSizeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return false;
        }
        
        // Get category code
        $categoryCode = $this->getCategoryCode($row['category_name']);
        
        // Format size code
        $sizeCode = str_replace('.', '', $row['size_name']);
        $sizeCode = strtoupper(substr($sizeCode, 0, 3));
        
        // Generate barcode
        $barcode = $this->generateBarcode(
            $categoryCode, 
            $row['product_id'], 
            $sizeCode
        );
        
        // Save to database
        $this->addBarcodeToProductSize($productSizeId, $barcode);
        
        return $barcode;
    }
    
    /**
     * Search product by barcode
     */
    public function getProductByBarcode($barcode) {
        $stmt = $this->db->prepare("
            SELECT 
                ps.product_size_id,
                ps.barcode,
                p.product_id,
                p.product_name,
                p.description,
                c.category_name,
                g.gender_name,
                ag.age_group_name,
                COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS size,
                p.price + ps.price_adjustment AS final_price,
                p.cost_price,
                ps.stock_quantity,
                ps.is_available,
                p.image_url
            FROM product_sizes ps
            JOIN products p ON ps.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN gender_sections g ON p.gender_id = g.gender_id
            LEFT JOIN age_groups ag ON p.age_group_id = ag.age_group_id
            LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
            LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
            WHERE ps.barcode = ?
        ");
        
        $stmt->execute([$barcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate barcodes for all products without barcodes
     */
    public function generateAllBarcodes() {
        $stmt = $this->db->query("
            SELECT product_size_id 
            FROM product_sizes 
            WHERE barcode IS NULL OR barcode = ''
        ");
        
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($this->generateAndSaveBarcode($row['product_size_id'])) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Check if barcode exists
     */
    public function barcodeExists($barcode) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM product_sizes 
            WHERE barcode = ?
        ");
        
        $stmt->execute([$barcode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    /**
     * Get all barcodes for a product
     */
    public function getProductBarcodes($productId) {
        $stmt = $this->db->prepare("
            SELECT 
                ps.barcode,
                COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS size,
                ps.stock_quantity,
                ps.is_available
            FROM product_sizes ps
            LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
            LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
            WHERE ps.product_id = ?
            ORDER BY COALESCE(cs.size_order, ss.size_us)
        ");
        
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ============================================
// USAGE EXAMPLES
// ============================================

// Initialize
$db = new PDO('mysql:host=localhost;dbname=trendywear_store', 'root', '');
$barcodeSystem = new AtliereBarcode($db);

// Example 1: Generate barcode for a specific product size
$barcode = $barcodeSystem->generateAndSaveBarcode(1);
echo "Generated barcode: $barcode\n";

// Example 2: Scan a barcode (POS system)
$scannedBarcode = 'ATL-TS0001-M0-56';
$product = $barcodeSystem->getProductByBarcode($scannedBarcode);

if ($product) {
    echo "Product: {$product['product_name']}\n";
    echo "Size: {$product['size']}\n";
    echo "Price: \${$product['final_price']}\n";
    echo "Stock: {$product['stock_quantity']}\n";
} else {
    echo "Product not found!\n";
}

// Example 3: Validate barcode format
$isValid = $barcodeSystem->validateBarcode('ATL-TS0001-M0-56');
echo "Barcode valid: " . ($isValid ? 'Yes' : 'No') . "\n";

// Example 4: Parse barcode
$parsed = $barcodeSystem->parseBarcode('ATL-TS0001-M0-56');
print_r($parsed);

// Example 5: Generate all missing barcodes
$count = $barcodeSystem->generateAllBarcodes();
echo "Generated $count barcodes\n";

// Example 6: Get all barcodes for a product
$barcodes = $barcodeSystem->getProductBarcodes(1);
foreach ($barcodes as $item) {
    echo "Barcode: {$item['barcode']} - Size: {$item['size']} - Stock: {$item['stock_quantity']}\n";
}

// ============================================
// API ENDPOINTS (REST API Example)
// ============================================

// Scan barcode endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'scan') {
    $barcode = $_POST['barcode'] ?? '';
    
    if (!$barcodeSystem->validateBarcode($barcode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid barcode format']);
        exit;
    }
    
    $product = $barcodeSystem->getProductByBarcode($barcode);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
}

// Generate barcode endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'generate') {
    $productSizeId = $_POST['product_size_id'] ?? 0;
    
    $barcode = $barcodeSystem->generateAndSaveBarcode($productSizeId);
    
    if ($barcode) {
        echo json_encode([
            'success' => true,
            'barcode' => $barcode
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate barcode']);
    }
}

?>