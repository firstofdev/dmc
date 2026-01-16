<?php
/**
 * Database upgrade script for new requirements
 * This script adds necessary fields and tables for the new features
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Database Upgrade</title></head><body>";
echo "<h2>Upgrading Database Schema...</h2>";

$upgrades = [];

// Check and add payment_frequency to contracts table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'payment_frequency'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN payment_frequency ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly' AFTER status");
        $upgrades[] = "✓ Added payment_frequency field to contracts table";
    } else {
        $upgrades[] = "• payment_frequency field already exists in contracts table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding payment_frequency: " . $e->getMessage();
}

// Check and add contract_pdf to contracts table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'contract_pdf'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN contract_pdf VARCHAR(255) DEFAULT NULL AFTER signature_img");
        $upgrades[] = "✓ Added contract_pdf field to contracts table";
    } else {
        $upgrades[] = "• contract_pdf field already exists in contracts table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding contract_pdf: " . $e->getMessage();
}

// Check and add tax_included, tax_percent, tax_amount to contracts table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'tax_included'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_included TINYINT(1) DEFAULT 0 AFTER total_amount");
        $upgrades[] = "✓ Added tax_included field to contracts table";
    } else {
        $upgrades[] = "• tax_included field already exists in contracts table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding tax_included: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'tax_percent'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_percent DECIMAL(5,2) DEFAULT 0.00 AFTER tax_included");
        $upgrades[] = "✓ Added tax_percent field to contracts table";
    } else {
        $upgrades[] = "• tax_percent field already exists in contracts table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding tax_percent: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'tax_amount'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0.00 AFTER tax_percent");
        $upgrades[] = "✓ Added tax_amount field to contracts table";
    } else {
        $upgrades[] = "• tax_amount field already exists in contracts table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding tax_amount: " . $e->getMessage();
}

// Create contract_services table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT NOT NULL,
        service_type ENUM('electricity', 'water', 'internet', 'gas', 'other') NOT NULL,
        service_name VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(15,2) DEFAULT 0.00,
        billing_frequency ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $upgrades[] = "✓ Created contract_services table";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $upgrades[] = "• contract_services table already exists";
    } else {
        $upgrades[] = "✗ Error creating contract_services table: " . $e->getMessage();
    }
}

// Add partial payment support to payments table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'original_amount'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN original_amount DECIMAL(15,2) DEFAULT NULL AFTER amount");
        $upgrades[] = "✓ Added original_amount field to payments table";
    } else {
        $upgrades[] = "• original_amount field already exists in payments table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding original_amount: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'remaining_amount'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN remaining_amount DECIMAL(15,2) DEFAULT 0.00 AFTER original_amount");
        $upgrades[] = "✓ Added remaining_amount field to payments table";
    } else {
        $upgrades[] = "• remaining_amount field already exists in payments table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding remaining_amount: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_type ENUM('full', 'partial', 'deferred') DEFAULT 'full' AFTER status");
        $upgrades[] = "✓ Added payment_type field to payments table";
    } else {
        $upgrades[] = "• payment_type field already exists in payments table";
    }
} catch (Exception $e) {
    $upgrades[] = "✗ Error adding payment_type: " . $e->getMessage();
}

// Display results
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f5f5f5; border-radius: 8px;'>";
echo "<h3 style='color: #333;'>Upgrade Results:</h3>";
echo "<ul style='list-style: none; padding: 0;'>";
foreach ($upgrades as $msg) {
    $color = '#28a745';
    if (strpos($msg, '✗') === 0) $color = '#dc3545';
    elseif (strpos($msg, '•') === 0) $color = '#6c757d';
    echo "<li style='padding: 8px; margin: 5px 0; background: white; border-left: 4px solid $color; border-radius: 4px;'>$msg</li>";
}
echo "</ul>";
echo "<div style='margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;'>";
echo "<strong>⚠️ Important:</strong> Delete this file (upgrade_requirements.php) after successful upgrade for security reasons.";
echo "</div>";
echo "<div style='margin-top: 15px;'>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Return to Dashboard</a>";
echo "</div>";
echo "</div>";

echo "</body></html>";
?>
