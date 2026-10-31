ALTER TABLE loan_product MODIFY interest_method ENUM('flat','reducing','fixed') NOT NULL DEFAULT 'fixed';
ALTER TABLE loans MODIFY interest_method ENUM('flat','reducing','fixed') NOT NULL;
UPDATE loan_product SET maximum_amount = 20000.00, minimum_amount = 100.00, annual_interest_rate = 10.0000, interest_method = 'fixed', minimum_term_months = 6, maximum_term_months = 6, processing_fee = 0.00 WHERE id = 1;
