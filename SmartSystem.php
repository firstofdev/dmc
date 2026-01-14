<?php
// SmartSystem.php
class SmartSystem {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function safeFetchColumn(string $sql, array $params = [], $default = 0) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return $value !== false && $value !== null ? $value : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    private function safeFetchAll(string $sql, array $params = []): array {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function postJson($url, array $payload, array $headers = []) {
        $ch = curl_init($url);
        $allHeaders = array_merge(['Content-Type: application/json'], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'error' => $error,
            'body' => $response,
        ];
    }

    // تحليل الهوية (OCR)
    public function analyzeIDCard($imagePath) {
        if (!ocr_api_url() || !ocr_api_key()) {
            return [
                'success' => false,
                'error' => 'OCR غير مُعد. تأكد من ضبط OCR_API_URL و OCR_API_KEY.',
            ];
        }

        if (!is_file($imagePath)) {
            return [
                'success' => false,
                'error' => 'مسار الصورة غير صالح.',
            ];
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $payload = [
            'image' => $imageData,
        ];

        $result = $this->postJson(ocr_api_url(), $payload, [
            'Authorization: Bearer '.ocr_api_key(),
        ]);

        if ($result['error'] || $result['status'] >= 400) {
            return [
                'success' => false,
                'error' => 'فشل استدعاء خدمة OCR.',
                'details' => $result['error'] ?: $result['body'],
            ];
        }

        $data = json_decode($result['body'], true);
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    // إرسال واتساب (تسجيل في activity_log المرفق)
    public function sendWhatsApp($phone, $message) {
        if (!is_whatsapp_configured()) {
            $stmt = $this->pdo->prepare("INSERT INTO activity_log (description, type) VALUES (?, 'whatsapp_error')");
            $stmt->execute(["تعذر إرسال رسالة واتساب لعدم ضبط التوكن."]);
            return false;
        }

        $payload = [
            'token' => whatsapp_token(),
            'to' => $phone,
            'body' => $message,
        ];

        $result = $this->postJson(whatsapp_api_url(), $payload);
        if ($result['error'] || $result['status'] >= 400) {
            $stmt = $this->pdo->prepare("INSERT INTO activity_log (description, type) VALUES (?, 'whatsapp_error')");
            $stmt->execute(["فشل إرسال رسالة واتساب إلى $phone."]);
            return false;
        }

        $stmt = $this->pdo->prepare("INSERT INTO activity_log (description, type) VALUES (?, 'whatsapp_sent')");
        $stmt->execute(["تم إرسال رسالة لـ $phone: $message"]);
        return true;
    }

    public function getCashflowForecast(): array {
        $today = date('Y-m-d');
        $in30 = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status != 'paid' AND due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)",
            [$today, $today],
            0
        );
        $in60 = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status != 'paid' AND due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 60 DAY)",
            [$today, $today],
            0
        );
        $in90 = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status != 'paid' AND due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 90 DAY)",
            [$today, $today],
            0
        );
        $overdueAmount = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status != 'paid' AND due_date < ?",
            [$today],
            0
        );
        $paidLast90 = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_date >= DATE_SUB(?, INTERVAL 90 DAY)",
            [$today],
            0
        );
        $billedLast90 = $this->safeFetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE due_date >= DATE_SUB(?, INTERVAL 90 DAY)",
            [$today],
            0
        );
        $collectionTrend = $billedLast90 > 0 ? round(($paidLast90 / $billedLast90) * 100, 1) : 0;

        return [
            'in_30' => (float) $in30,
            'in_60' => (float) $in60,
            'in_90' => (float) $in90,
            'overdue' => (float) $overdueAmount,
            'collection_trend' => $collectionTrend,
        ];
    }

    public function getMaintenancePulse(): array {
        $pending = (int) $this->safeFetchColumn(
            "SELECT COUNT(*) FROM maintenance WHERE status = 'pending'",
            [],
            0
        );
        $avgCost90 = (float) $this->safeFetchColumn(
            "SELECT COALESCE(AVG(cost),0) FROM maintenance WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND cost IS NOT NULL",
            [],
            0
        );
        $repeatUnits = (int) $this->safeFetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT unit_id, COUNT(*) AS cnt
                FROM maintenance
                WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY unit_id
                HAVING cnt >= 2
            ) t",
            [],
            0
        );
        $emergencyCount = (int) $this->safeFetchColumn(
            "SELECT COUNT(*) FROM maintenance WHERE status='pending' AND (description LIKE '%حريق%' OR description LIKE '%تماس%' OR description LIKE '%انفجار%' OR description LIKE '%ماس كهربائي%')",
            [],
            0
        );

        $riskScore = min(100, ($pending * 5) + ($repeatUnits * 10) + ($emergencyCount * 15));

        return [
            'pending' => $pending,
            'avg_cost_90' => round($avgCost90, 2),
            'repeat_units' => $repeatUnits,
            'emergency' => $emergencyCount,
            'risk_score' => $riskScore,
        ];
    }

    public function getTenantRiskSnapshot(): array {
        $tenantNameColumn = tenant_name_column($this->pdo);
        $rows = $this->safeFetchAll(
            "SELECT t.id, t.$tenantNameColumn AS full_name, t.phone,
                COUNT(p.id) AS overdue_count,
                COALESCE(SUM(p.amount),0) AS overdue_amount,
                COALESCE(MAX(DATEDIFF(CURDATE(), p.due_date)),0) AS max_overdue_days
            FROM payments p
            JOIN contracts c ON p.contract_id = c.id
            JOIN tenants t ON c.tenant_id = t.id
            WHERE p.status != 'paid' AND p.due_date < CURDATE()
            GROUP BY t.id",
            []
        );

        $highRisk = 0;
        $maxOverdueDays = 0;
        foreach ($rows as &$row) {
            $score = ($row['overdue_count'] * 10) + ($row['max_overdue_days'] * 0.5) + ($row['overdue_amount'] / 1000);
            $row['risk_score'] = round($score, 1);
            if ($score >= 40) {
                $highRisk++;
            }
            $maxOverdueDays = max($maxOverdueDays, (int) $row['max_overdue_days']);
        }
        unset($row);

        return [
            'high_risk_count' => $highRisk,
            'max_overdue_days' => $maxOverdueDays,
            'items' => $rows,
        ];
    }

    public function buildPaymentLink(?int $paymentId): ?string {
        if (!$paymentId) {
            return null;
        }
        if (!payment_portal_url()) {
            return null;
        }
        $portalUrl = payment_portal_url();
        $separator = str_contains($portalUrl, '?') ? '&' : '?';
        return $portalUrl.$separator.'payment_id='.urlencode((string) $paymentId);
    }

    public function analyzeMaintenance(string $description, ?float $cost = null): array {
        $text = mb_strtolower(trim($description), 'UTF-8');
        $priority = 'medium';
        $signals = [];

        $emergencyKeywords = ['حريق', 'تماس', 'كهرباء', 'انفجار', 'تسرب كبير', 'ماس كهربائي'];
        $highKeywords = ['تسرب', 'انقطاع', 'عطل', 'سقف', 'ماء', 'تصريف', 'مكيف'];
        foreach ($emergencyKeywords as $word) {
            if (mb_strpos($text, $word) !== false) {
                $priority = 'emergency';
                $signals[] = "كلمة مفتاحية: $word";
                break;
            }
        }
        if ($priority !== 'emergency') {
            foreach ($highKeywords as $word) {
                if (mb_strpos($text, $word) !== false) {
                    $priority = 'high';
                    $signals[] = "كلمة مفتاحية: $word";
                    break;
                }
            }
        }

        if ($cost !== null && $cost >= 5000 && $priority === 'medium') {
            $priority = 'high';
            $signals[] = 'تكلفة مرتفعة';
        }

        if ($priority === 'medium' && $cost !== null && $cost <= 500) {
            $priority = 'low';
            $signals[] = 'تكلفة منخفضة';
        }

        $avgCost = (float) $this->safeFetchColumn(
            "SELECT COALESCE(AVG(cost),0) FROM maintenance WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND cost IS NOT NULL",
            [],
            0
        );
        if ($cost !== null && $avgCost > 0 && $cost >= ($avgCost * 1.4) && $priority !== 'emergency') {
            $priority = 'high';
            $signals[] = 'تكلفة أعلى من المتوسط';
        }

        $analysis = $signals
            ? 'تحليل ذكي: '.$priority.' بناءً على '.implode('، ', $signals)
            : 'تحليل ذكي: حالة قياسية تتطلب متابعة دورية.';

        return [
            'priority' => $priority,
            'analysis' => $analysis,
        ];
    }

    public function generateRecommendations(array $cashflow, array $maintenancePulse, array $tenantRisk): array {
        $recommendations = [];

        if ($cashflow['overdue'] > 0) {
            $recommendations[] = 'إطلاق حملة تحصيل مركزة للدفعات المتأخرة مع خطط سداد مرنة.';
        }
        if ($cashflow['collection_trend'] < 80) {
            $recommendations[] = 'رفع معدل التحصيل عبر تذكيرات واتساب قبل الاستحقاق بـ 7 أيام.';
        }
        if ($maintenancePulse['risk_score'] >= 60) {
            $recommendations[] = 'تنفيذ صيانة وقائية للوحدات المتكررة الأعطال خلال 30 يوماً.';
        }
        if ($tenantRisk['high_risk_count'] > 0) {
            $recommendations[] = 'تفعيل متابعة شخصية للمستأجرين مرتفعي المخاطر.';
        }

        return $recommendations;
    }

    /**
     * تحليل أداء العقارات وتصنيفها
     */
    public function analyzePropertyPerformance(): array {
        $properties = $this->safeFetchAll(
            "SELECT p.id, p.name,
                COUNT(DISTINCT u.id) AS total_units,
                SUM(CASE WHEN u.status='rented' THEN 1 ELSE 0 END) AS rented_units,
                COALESCE(SUM(CASE WHEN u.status='rented' THEN u.yearly_price ELSE 0 END), 0) AS potential_revenue,
                COUNT(DISTINCT m.id) AS maintenance_count,
                COALESCE(SUM(m.cost), 0) AS maintenance_cost
            FROM properties p
            LEFT JOIN units u ON u.property_id = p.id
            LEFT JOIN maintenance m ON m.property_id = p.id AND m.request_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY p.id
            ORDER BY rented_units DESC, potential_revenue DESC",
            []
        );

        foreach ($properties as &$prop) {
            $total = (int) $prop['total_units'];
            $rented = (int) $prop['rented_units'];
            $prop['occupancy_rate'] = $total > 0 ? round(($rented / $total) * 100, 1) : 0;
            $prop['maintenance_ratio'] = $prop['potential_revenue'] > 0 
                ? round(($prop['maintenance_cost'] / $prop['potential_revenue']) * 100, 1) 
                : 0;
            
            // تصنيف الأداء
            if ($prop['occupancy_rate'] >= 90 && $prop['maintenance_ratio'] < 10) {
                $prop['performance_grade'] = 'ممتاز';
                $prop['grade_color'] = '#10b981';
            } elseif ($prop['occupancy_rate'] >= 75 && $prop['maintenance_ratio'] < 15) {
                $prop['performance_grade'] = 'جيد';
                $prop['grade_color'] = '#3b82f6';
            } elseif ($prop['occupancy_rate'] >= 60) {
                $prop['performance_grade'] = 'مقبول';
                $prop['grade_color'] = '#f59e0b';
            } else {
                $prop['performance_grade'] = 'يحتاج تحسين';
                $prop['grade_color'] = '#ef4444';
            }
        }
        unset($prop);

        return $properties;
    }

    /**
     * توقع الإيرادات للأشهر القادمة بناءً على البيانات التاريخية
     */
    public function predictRevenue(int $months = 3): array {
        $historicalData = $this->safeFetchAll(
            "SELECT DATE_FORMAT(paid_date, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE status = 'paid' AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
            ORDER BY month ASC",
            []
        );

        if (count($historicalData) < 3) {
            return ['error' => 'بيانات غير كافية للتوقع'];
        }

        // حساب المتوسط المتحرك
        $amounts = array_map(function($row) { return (float) $row['total']; }, $historicalData);
        $avg = array_sum($amounts) / count($amounts);
        
        // حساب الاتجاه (بسيط)
        $lastThree = array_slice($amounts, -3);
        $firstThree = array_slice($amounts, 0, 3);
        $trendChange = (array_sum($lastThree) / 3) - (array_sum($firstThree) / 3);
        $trendPercent = (array_sum($firstThree) / 3) > 0 
            ? ($trendChange / (array_sum($firstThree) / 3)) * 100 
            : 0;

        $predictions = [];
        $baseValue = end($amounts);
        for ($i = 1; $i <= $months; $i++) {
            $predicted = $baseValue + ($trendChange * ($i / 3));
            $predictions[] = [
                'month' => date('Y-m', strtotime("+{$i} month")),
                'predicted_amount' => round(max(0, $predicted), 2),
                'confidence' => count($historicalData) >= 6 ? 'متوسط' : 'منخفض'
            ];
        }

        return [
            'predictions' => $predictions,
            'trend' => $trendPercent > 0 ? 'صاعد' : ($trendPercent < 0 ? 'نازل' : 'مستقر'),
            'trend_percent' => round($trendPercent, 2),
            'avg_monthly' => round($avg, 2)
        ];
    }

    /**
     * اقتراح أسعار الوحدات بناءً على السوق
     */
    public function suggestUnitPricing(int $unitId): array {
        $unit = $this->safeFetchAll(
            "SELECT u.*, p.type AS property_type, p.address
            FROM units u
            LEFT JOIN properties p ON u.property_id = p.id
            WHERE u.id = ?",
            [$unitId]
        );

        if (empty($unit)) {
            return ['error' => 'الوحدة غير موجودة'];
        }

        $unit = $unit[0];
        $currentPrice = (float) ($unit['yearly_price'] ?? 0);

        // مقارنة مع وحدات مشابهة
        $similarUnits = $this->safeFetchAll(
            "SELECT AVG(yearly_price) AS avg_price, MIN(yearly_price) AS min_price, MAX(yearly_price) AS max_price
            FROM units
            WHERE type = ? AND yearly_price > 0 AND id != ?",
            [$unit['type'], $unitId]
        );

        $avgMarket = !empty($similarUnits) ? (float) $similarUnits[0]['avg_price'] : $currentPrice;
        $minMarket = !empty($similarUnits) ? (float) $similarUnits[0]['min_price'] : $currentPrice;
        $maxMarket = !empty($similarUnits) ? (float) $similarUnits[0]['max_price'] : $currentPrice;

        $suggestion = '';
        $recommended = $currentPrice;

        if ($currentPrice === 0) {
            $suggestion = 'لم يتم تحديد سعر للوحدة. السعر المقترح بناءً على السوق: ' . number_format($avgMarket, 2);
            $recommended = $avgMarket;
        } elseif ($currentPrice < ($avgMarket * 0.85)) {
            $suggestion = 'السعر الحالي أقل من السوق بنسبة كبيرة. يمكن رفع السعر إلى ' . number_format($avgMarket * 0.95, 2);
            $recommended = $avgMarket * 0.95;
        } elseif ($currentPrice > ($avgMarket * 1.15)) {
            $suggestion = 'السعر الحالي أعلى من السوق. قد يؤثر على الإشغال. السعر المقترح: ' . number_format($avgMarket * 1.05, 2);
            $recommended = $avgMarket * 1.05;
        } else {
            $suggestion = 'السعر الحالي مناسب ومتوافق مع السوق.';
            $recommended = $currentPrice;
        }

        return [
            'current_price' => $currentPrice,
            'market_avg' => round($avgMarket, 2),
            'market_range' => [round($minMarket, 2), round($maxMarket, 2)],
            'recommended_price' => round($recommended, 2),
            'suggestion' => $suggestion
        ];
    }

    /**
     * كشف أنماط السداد للمستأجرين
     */
    public function detectPaymentPatterns(int $tenantId): array {
        $payments = $this->safeFetchAll(
            "SELECT p.*, DATEDIFF(p.paid_date, p.due_date) AS days_delay
            FROM payments p
            JOIN contracts c ON p.contract_id = c.id
            WHERE c.tenant_id = ? AND p.status = 'paid'
            ORDER BY p.due_date DESC
            LIMIT 12",
            [$tenantId]
        );

        if (empty($payments)) {
            return ['pattern' => 'لا توجد بيانات كافية', 'reliability' => 'غير معروف'];
        }

        $totalPayments = count($payments);
        $onTimePayments = 0;
        $totalDelay = 0;

        foreach ($payments as $payment) {
            $delay = (int) ($payment['days_delay'] ?? 0);
            if ($delay <= 0) {
                $onTimePayments++;
            }
            if ($delay > 0) {
                $totalDelay += $delay;
            }
        }

        $onTimeRate = round(($onTimePayments / $totalPayments) * 100, 1);
        $avgDelay = $totalDelay > 0 ? round($totalDelay / max(1, $totalPayments - $onTimePayments), 1) : 0;

        $reliability = 'ممتاز';
        $pattern = 'يدفع في الوقت المحدد';
        
        if ($onTimeRate >= 90) {
            $reliability = 'ممتاز';
            $pattern = 'يدفع في الوقت المحدد دائماً';
        } elseif ($onTimeRate >= 75) {
            $reliability = 'جيد';
            $pattern = 'يدفع في الوقت المحدد غالباً';
        } elseif ($onTimeRate >= 50) {
            $reliability = 'متوسط';
            $pattern = 'يتأخر أحياناً في الدفع';
        } else {
            $reliability = 'ضعيف';
            $pattern = 'يتأخر كثيراً في الدفع';
        }

        return [
            'pattern' => $pattern,
            'reliability' => $reliability,
            'on_time_rate' => $onTimeRate,
            'avg_delay_days' => $avgDelay,
            'total_payments' => $totalPayments
        ];
    }
}
$AI = new SmartSystem($pdo);
?>
