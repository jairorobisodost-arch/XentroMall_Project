<?php
/**
 * Business Structure Detection System
 * Automatically detects business structure from submitted documents
 */

class BusinessStructureDetector {
    
    /**
     * Analyze documents and detect business structure
     */
    public static function detectBusinessStructure($documents, $tenantDetails = []) {
        $businessStructure = 'unknown';
        $registrationType = 'unknown';
        $tinNumber = null;
        
        // Extract text from document filenames and content
        $documentInfo = self::extractDocumentInfo($documents);
        
        // Analyze BIR Registration for TIN and structure
        if (!empty($documents['bir_registration'])) {
            $birInfo = self::analyzeBIRDocument($documents['bir_registration']);
            if ($birInfo['tin_number']) {
                $tinNumber = $birInfo['tin_number'];
            }
            if ($birInfo['business_type']) {
                $businessStructure = $birInfo['business_type'];
            }
        }
        
        // Analyze Business Registration document
        if (!empty($documents['business_registration'])) {
            $regInfo = self::analyzeBusinessRegistration($documents['business_registration']);
            if ($regInfo['registration_type']) {
                $registrationType = $regInfo['registration_type'];
            }
            if ($regInfo['business_type'] && $businessStructure === 'unknown') {
                $businessStructure = $regInfo['business_type'];
            }
        }
        
        // Analyze company name and trade name patterns
        if (!empty($tenantDetails['company_name']) || !empty($tenantDetails['tradename'])) {
            $nameAnalysis = self::analyzeBusinessNames(
                $tenantDetails['company_name'] ?? '', 
                $tenantDetails['tradename'] ?? ''
            );
            
            if ($businessStructure === 'unknown' && $nameAnalysis['business_type']) {
                $businessStructure = $nameAnalysis['business_type'];
            }
        }
        
        // Final determination logic
        $businessStructure = self::finalBusinessDetermination(
            $businessStructure, 
            $registrationType, 
            $documentInfo
        );
        
        return [
            'business_structure' => $businessStructure,
            'business_registration_type' => $registrationType,
            'tin_number' => $tinNumber,
            'confidence_score' => self::calculateConfidence($businessStructure, $registrationType, $tinNumber)
        ];
    }
    
    /**
     * Extract information from document filenames and basic content
     */
    private static function extractDocumentInfo($documents) {
        $info = [
            'has_sec_document' => false,
            'has_dti_document' => false,
            'has_corporation_keywords' => false,
            'has_proprietor_keywords' => false
        ];
        
        foreach ($documents as $docType => $filePath) {
            if (empty($filePath)) continue;
            
            $filename = strtolower(basename($filePath));
            
            // Check for SEC registration (Corporation)
            if (strpos($filename, 'sec') !== false || 
                strpos($filename, 'securities') !== false ||
                strpos($filename, 'corporation') !== false) {
                $info['has_sec_document'] = true;
            }
            
            // Check for DTI registration (Sole Proprietorship)
            if (strpos($filename, 'dti') !== false || 
                strpos($filename, 'trade') !== false ||
                strpos($filename, 'business name') !== false) {
                $info['has_dti_document'] = true;
            }
            
            // Check for corporation keywords
            if (strpos($filename, 'corp') !== false || 
                strpos($filename, 'inc') !== false ||
                strpos($filename, 'corporation') !== false ||
                strpos($filename, 'company') !== false) {
                $info['has_corporation_keywords'] = true;
            }
            
            // Check for proprietor keywords
            if (strpos($filename, 'proprietor') !== false || 
                strpos($filename, 'sole') !== false ||
                strpos($filename, 'individual') !== false) {
                $info['has_proprietor_keywords'] = true;
            }
        }
        
        return $info;
    }
    
    /**
     * Analyze BIR document for TIN and business type
     */
    private static function analyzeBIRDocument($filePath) {
        $info = [
            'tin_number' => null,
            'business_type' => null
        ];
        
        $filename = strtolower(basename($filePath));
        
        // Extract TIN from filename (common pattern: TIN_123456789)
        if (preg_match('/tin[_\s-]*(\d[\d\s-]{8,15})/', $filename, $matches)) {
            $info['tin_number'] = preg_replace('/[\s-]/', '', $matches[1]);
        }
        
        // Check for business type indicators in BIR document
        if (strpos($filename, 'corporation') !== false || strpos($filename, 'corp') !== false) {
            $info['business_type'] = 'corporation';
        } elseif (strpos($filename, 'proprietor') !== false || strpos($filename, 'sole') !== false) {
            $info['business_type'] = 'sole_proprietorship';
        } elseif (strpos($filename, 'partnership') !== false) {
            $info['business_type'] = 'partnership';
        }
        
        return $info;
    }
    
    /**
     * Analyze business registration document
     */
    private static function analyzeBusinessRegistration($filePath) {
        $info = [
            'registration_type' => null,
            'business_type' => null
        ];
        
        $filename = strtolower(basename($filePath));
        
        // Determine registration type
        if (strpos($filename, 'sec') !== false || strpos($filename, 'securities') !== false) {
            $info['registration_type'] = 'SEC';
            $info['business_type'] = 'corporation';
        } elseif (strpos($filename, 'dti') !== false || strpos($filename, 'trade') !== false) {
            $info['registration_type'] = 'DTI';
            $info['business_type'] = 'sole_proprietorship';
        } elseif (strpos($filename, 'cda') !== false || strpos($filename, 'cooperative') !== false) {
            $info['registration_type'] = 'CDA';
            $info['business_type'] = 'cooperative';
        }
        
        return $info;
    }
    
    /**
     * Analyze business names for structure indicators
     */
    private static function analyzeBusinessNames($companyName, $tradeName) {
        $info = ['business_type' => null];
        
        $combinedName = strtolower($companyName . ' ' . $tradeName);
        
        // Corporation indicators
        if (preg_match('/\b(corp|corporation|inc|incorporated|company)\b/', $combinedName)) {
            $info['business_type'] = 'corporation';
        }
        // Partnership indicators
        elseif (preg_match('/\b(partners|partnership|and|&)\b/', $combinedName)) {
            $info['business_type'] = 'partnership';
        }
        // Cooperative indicators
        elseif (preg_match('/\b(coop|cooperative|multipurpose)\b/', $combinedName)) {
            $info['business_type'] = 'cooperative';
        }
        // LLC indicators
        elseif (preg_match('/\b(llc|l\.l\.c)\b/', $combinedName)) {
            $info['business_type'] = 'llc';
        }
        // Sole proprietorship (default if personal name)
        elseif (preg_match('/^[a-z\s]+(trading|dba|doing business as)/', $combinedName)) {
            $info['business_type'] = 'sole_proprietorship';
        }
        
        return $info;
    }
    
    /**
     * Final business structure determination
     */
    private static function finalBusinessDetermination($businessStructure, $registrationType, $documentInfo) {
        // High confidence determinations
        if ($registrationType === 'SEC') {
            return 'corporation';
        } elseif ($registrationType === 'DTI') {
            return 'sole_proprietorship';
        } elseif ($registrationType === 'CDA') {
            return 'cooperative';
        }
        
        // Document-based determinations
        if ($documentInfo['has_sec_document']) {
            return 'corporation';
        } elseif ($documentInfo['has_dti_document']) {
            return 'sole_proprietorship';
        }
        
        // Keyword-based determinations
        if ($documentInfo['has_corporation_keywords'] && !$documentInfo['has_proprietor_keywords']) {
            return 'corporation';
        } elseif ($documentInfo['has_proprietor_keywords'] && !$documentInfo['has_corporation_keywords']) {
            return 'sole_proprietorship';
        }
        
        return $businessStructure; // Return whatever was detected or 'unknown'
    }
    
    /**
     * Calculate confidence score for the detection
     */
    private static function calculateConfidence($businessStructure, $registrationType, $tinNumber) {
        $score = 0;
        
        // High confidence if we have official registration
        if (in_array($registrationType, ['SEC', 'DTI', 'CDA'])) {
            $score += 40;
        }
        
        // Medium confidence if TIN is detected
        if ($tinNumber) {
            $score += 30;
        }
        
        // Low confidence if business structure is determined
        if ($businessStructure !== 'unknown') {
            $score += 30;
        }
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Get business structure display information
     */
    public static function getBusinessStructureInfo($structure) {
        $structures = [
            'sole_proprietorship' => [
                'name' => 'Sole Proprietorship',
                'description' => 'Single owner business',
                'registration' => 'DTI Registration',
                'color' => 'blue'
            ],
            'corporation' => [
                'name' => 'Corporation',
                'description' => 'Registered corporation',
                'registration' => 'SEC Registration',
                'color' => 'green'
            ],
            'partnership' => [
                'name' => 'Partnership',
                'description' => 'Business partnership',
                'registration' => 'SEC Registration',
                'color' => 'purple'
            ],
            'llc' => [
                'name' => 'Limited Liability Company',
                'description' => 'LLC structure',
                'registration' => 'SEC Registration',
                'color' => 'orange'
            ],
            'cooperative' => [
                'name' => 'Cooperative',
                'description' => 'Member-owned cooperative',
                'registration' => 'CDA Registration',
                'color' => 'yellow'
            ],
            'unknown' => [
                'name' => 'Unknown',
                'description' => 'Business structure not determined',
                'registration' => 'Not specified',
                'color' => 'gray'
            ]
        ];
        
        return $structures[$structure] ?? $structures['unknown'];
    }
}

// Usage example:
// $detection = BusinessStructureDetector::detectBusinessStructure($documents, $tenantDetails);
// echo "Business Structure: " . $detection['business_structure'];
// echo "Registration Type: " . $detection['business_registration_type'];
// echo "TIN: " . $detection['tin_number'];
// echo "Confidence: " . $detection['confidence_score'] . "%";
?>
