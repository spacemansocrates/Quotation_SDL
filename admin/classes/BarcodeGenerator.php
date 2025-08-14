<?php
class BarcodeGenerator {
    private static $patterns = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    public static function generate($text, $height = 40, $scale = 3) {
        if (!extension_loaded('gd')) {
            throw new Exception('GD extension required');
        }

        // Ensure all parameters are integers
        $height = (int)$height;
        $scale = (int)$scale;
        
        // Minimum scale for readability
        if ($scale < 1) $scale = 1;

        // Clean the input text - remove asterisks and invalid characters
        $text = strtoupper($text);
        $text = str_replace('*', '', $text);
        $text = preg_replace('/[^A-Z0-9\-\.\s\$\/\+%]/', '', $text);
        
        $encoded = '*' . $text . '*';
        
        // Build sequence with better spacing
        $sequence = '';
        $characters = str_split($encoded);
        foreach ($characters as $index => $char) {
            if (!isset(self::$patterns[$char])) {
                throw new Exception("Invalid character in barcode: $char");
            }
            $sequence .= self::$patterns[$char];
            
            // Add inter-character gap (narrow space) except after last character
            if ($index < count($characters) - 1) {
                $sequence .= 'n'; // narrow gap between characters
            }
        }
        
        // Integer-only scaling for better readability (no floats!)
        $narrow = $scale;
        $wide = $scale * 2; // Use 2x instead of 2.5x to avoid decimals
        
        // Calculate total width
        $width = 0;
        foreach (str_split($sequence) as $c) {
            $w = ($c === 'n') ? $narrow : $wide;
            $width += $w;
        }
        
        // Add padding on sides for quiet zones (important for scanning)
        $quietZone = $narrow * 10;
        $totalWidth = $width + ($quietZone * 2);
        
        // Create image with better proportions
        $img = imagecreatetruecolor($totalWidth, $height + 35);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $gray = imagecolorallocate($img, 128, 128, 128);
        imagefill($img, 0, 0, $white);
        
        // Draw quiet zone indicators (light gray lines)
        imageline($img, $quietZone - 1, 5, $quietZone - 1, $height + 15, $gray);
        imageline($img, $totalWidth - $quietZone, 5, $totalWidth - $quietZone, $height + 15, $gray);
        
        // Draw barcode with proper quiet zones
        $x = $quietZone;
        $bar = true;
        foreach (str_split($sequence) as $c) {
            $w = ($c === 'n') ? $narrow : $wide;
            if ($bar) {
                // All coordinates are already integers - no casting needed
                imagefilledrectangle($img, $x, 10, $x + $w - 1, $height + 9, $black);
            }
            $x += $w;
            $bar = !$bar;
        }
        
        // Improved text rendering
        $fontSize = 3; // Larger font
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textX = (int)(($totalWidth - $textWidth) / 2); // Cast division result to int
        $textY = $height + 15;
        
        // Add white background behind text for better readability
        imagefilledrectangle($img, $textX - 2, $textY - 2, $textX + $textWidth + 1, $textY + imagefontheight($fontSize) + 1, $white);
        imagestring($img, $fontSize, $textX, $textY, $text, $black);
        
        // Add dimensions text (helpful for debugging)
        $dimText = $totalWidth . 'x' . ($height + 35) . 'px';
        imagestring($img, 1, 5, $height + 25, $dimText, $gray);
        
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return base64_encode($data);
    }
    
    // Helper method to generate and display barcode directly
    public static function display($text, $height = 40, $scale = 3) {
        header('Content-Type: image/png');
        echo base64_decode(self::generate($text, $height, $scale));
    }
    
    // Method to save barcode to file
    public static function save($text, $filename, $height = 40, $scale = 3) {
        $data = base64_decode(self::generate($text, $height, $scale));
        return file_put_contents($filename, $data);
    }
}

// Example usage:
/*
try {
    // Generate and save barcode
    BarcodeGenerator::save('HELLO123', 'barcode.png', 40, 3);
    
    // Generate base64 for HTML
    $base64 = BarcodeGenerator::generate('TEST456');
    echo '<img src="data:image/png;base64,' . $base64 . '" alt="Barcode">';
    
    // Direct display (for web)
    // BarcodeGenerator::display('SAMPLE');
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
*/
?>