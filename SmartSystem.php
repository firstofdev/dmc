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
}
$AI = new SmartSystem($pdo);
?>
