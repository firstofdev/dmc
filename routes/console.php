use App\Models\Contract;
use Illuminate\Support\Facades\Log;

// هذا الكود يعمل يومياً بشكل تلقائي
Schedule::call(function () {
    $expiringContracts = Contract::where('end_date', '<', now()->addDays(30))
                                 ->where('status', 'active')
                                 ->get();

    foreach ($expiringContracts as $contract) {
        // هنا يمكن ربط خدمة WhatsApp API أو البريد الإلكتروني
        // مثال: إرسال بريد للمدير
        Log::info("تنبيه ذكي: العقد رقم {$contract->id} سينتهي قريباً للمستأجر {$contract->tenant->full_name}");
    }
})->daily();
