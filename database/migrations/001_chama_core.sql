CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(32) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    account_status ENUM('pending', 'active', 'suspended', 'rejected') NOT NULL DEFAULT 'pending',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    membership_number VARCHAR(40) NULL UNIQUE,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    national_id_encrypted TEXT NULL,
    national_id_lookup CHAR(64) NULL UNIQUE,
    date_of_birth DATE NULL,
    gender ENUM('Male', 'Female', 'Other', 'Prefer not to say') NULL,
    residential_address VARCHAR(255) NULL,
    date_joined DATE NULL,
    first_contribution_month DATE NULL,
    membership_status ENUM('Pending', 'Active', 'Suspended', 'Withdrawn', 'Deceased') NOT NULL DEFAULT 'Pending',
    next_of_kin_name VARCHAR(160) NULL,
    next_of_kin_phone VARCHAR(32) NULL,
    next_of_kin_relationship VARCHAR(80) NULL,
    remarks TEXT NULL,
    rejection_reason TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_members_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_members_status_name (membership_status, full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contribution_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monthly_amount DECIMAL(15,2) NOT NULL,
    due_day TINYINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL UNIQUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contribution_rules_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contribution_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    monthly_amount DECIMAL(15,2) NOT NULL,
    due_day TINYINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    reason VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contribution_override_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_contribution_override_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_contribution_override_dates (member_id, effective_from, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contribution_obligations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    period_month DATE NOT NULL,
    due_date DATE NOT NULL,
    expected_amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('upcoming', 'completed', 'overdue') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_contribution_obligation_member FOREIGN KEY (member_id) REFERENCES members(id),
    UNIQUE KEY uq_contribution_member_period (member_id, period_month),
    INDEX idx_contribution_status_due (status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loan_product (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    name VARCHAR(120) NOT NULL DEFAULT 'Member Loan',
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    minimum_amount DECIMAL(15,2) NOT NULL DEFAULT 1000.00,
    maximum_amount DECIMAL(15,2) NOT NULL DEFAULT 100000.00,
    annual_interest_rate DECIMAL(7,4) NOT NULL DEFAULT 12.0000,
    interest_method ENUM('flat', 'reducing') NOT NULL DEFAULT 'flat',
    minimum_term_months SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    maximum_term_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
    processing_fee DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    late_penalty_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    minimum_active_months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    minimum_contribution_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    maximum_active_loans TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_product_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loan_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    requested_amount DECIMAL(15,2) NOT NULL,
    requested_term_months SMALLINT UNSIGNED NOT NULL,
    purpose VARCHAR(500) NULL,
    status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'disbursed') NOT NULL DEFAULT 'submitted',
    approved_amount DECIMAL(15,2) NULL,
    product_snapshot JSON NULL,
    estimated_total DECIMAL(15,2) NOT NULL,
    review_notes TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_application_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_loan_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_loan_application_status (status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NULL UNIQUE,
    member_id BIGINT UNSIGNED NOT NULL,
    loan_number VARCHAR(40) NOT NULL UNIQUE,
    principal_amount DECIMAL(15,2) NOT NULL,
    annual_interest_rate DECIMAL(7,4) NOT NULL,
    interest_method ENUM('flat', 'reducing') NOT NULL,
    term_months SMALLINT UNSIGNED NOT NULL,
    processing_fee DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    late_penalty_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    outstanding_principal DECIMAL(15,2) NOT NULL,
    outstanding_interest DECIMAL(15,2) NOT NULL,
    outstanding_fees DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('approved', 'active', 'fully_repaid', 'closed', 'overdue', 'defaulted') NOT NULL DEFAULT 'approved',
    disbursed_on DATE NULL,
    disbursement_method VARCHAR(30) NULL,
    disbursement_reference VARCHAR(100) NULL,
    maturity_date DATE NULL,
    approved_by BIGINT UNSIGNED NOT NULL,
    disbursed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loans_application FOREIGN KEY (application_id) REFERENCES loan_applications(id),
    CONSTRAINT fk_loans_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_loans_approved_by FOREIGN KEY (approved_by) REFERENCES users(id),
    CONSTRAINT fk_loans_disbursed_by FOREIGN KEY (disbursed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_loans_member_status (member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loan_installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    principal_due DECIMAL(15,2) NOT NULL,
    interest_due DECIMAL(15,2) NOT NULL,
    fee_due DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    penalty_due DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    principal_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    interest_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    fee_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    penalty_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    penalty_assessed_at DATETIME NULL,
    status ENUM('upcoming', 'completed', 'overdue') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_installment_loan FOREIGN KEY (loan_id) REFERENCES loans(id),
    UNIQUE KEY uq_loan_installment (loan_id, installment_number),
    INDEX idx_installment_status_due (status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    payment_type ENUM('contribution', 'loan_repayment') NOT NULL,
    loan_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('Cash', 'M-Pesa', 'Bank Transfer', 'Other') NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    note VARCHAR(500) NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    review_note VARCHAR(500) NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_claim_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_payment_claim_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_claim_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_claim_method_reference (payment_method, reference_number),
    INDEX idx_payment_claim_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    payment_type ENUM('contribution', 'loan_repayment') NOT NULL,
    loan_id BIGINT UNSIGNED NULL,
    claim_id BIGINT UNSIGNED NULL UNIQUE,
    amount DECIMAL(15,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('Cash', 'M-Pesa', 'Bank Transfer', 'Other', 'Opening Balance') NOT NULL,
    reference_number VARCHAR(100) NOT NULL,
    source ENUM('claim', 'admin_direct', 'opening') NOT NULL,
    status ENUM('posted', 'reversed') NOT NULL DEFAULT 'posted',
    recorded_by BIGINT UNSIGNED NOT NULL,
    reversed_by BIGINT UNSIGNED NULL,
    reversal_reason VARCHAR(500) NULL,
    reversed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_financial_payment_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_financial_payment_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
    CONSTRAINT fk_financial_payment_claim FOREIGN KEY (claim_id) REFERENCES payment_claims(id) ON DELETE SET NULL,
    CONSTRAINT fk_financial_payment_recorder FOREIGN KEY (recorded_by) REFERENCES users(id),
    CONSTRAINT fk_financial_payment_reverser FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payment_method_reference (payment_method, reference_number),
    INDEX idx_payment_member_date (member_id, paid_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contribution_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    obligation_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL,
    allocation_type ENUM('obligation', 'credit') NOT NULL DEFAULT 'obligation',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contribution_allocation_payment FOREIGN KEY (payment_id) REFERENCES financial_payments(id),
    CONSTRAINT fk_contribution_allocation_obligation FOREIGN KEY (obligation_id) REFERENCES contribution_obligations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loan_payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NOT NULL,
    component ENUM('penalty', 'interest', 'principal', 'fee') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_allocation_payment FOREIGN KEY (payment_id) REFERENCES financial_payments(id),
    CONSTRAINT fk_loan_allocation_installment FOREIGN KEY (installment_id) REFERENCES loan_installments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link_url VARCHAR(255) NULL,
    delivery_channels JSON NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_member FOREIGN KEY (member_id) REFERENCES members(id),
    INDEX idx_notification_member_read (member_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    subject_type VARCHAR(80) NOT NULL,
    subject_id BIGINT UNSIGNED NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_subject (subject_type, subject_id),
    INDEX idx_audit_event_date (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('membership_number_prefix', 'KSSHG')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO loan_product (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = id;
