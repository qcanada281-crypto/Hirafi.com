<?php
// ==================== اتصال قاعدة البيانات ====================

class Database {
    private $host = 'localhost';
    private $port = 3308; // XAMPP MySQL custom port
    private $db_name = 'artisanats_com';
    private $user = 'root';
    private $password = '';
    private $conn;
    private $schema_file = __DIR__ . '/artisan_tables.sql';

    public function connect() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->password, $this->db_name, $this->port);

            if ($this->conn->connect_error) {
                $this->createDatabase();
                $this->conn = new mysqli($this->host, $this->user, $this->password, $this->db_name, $this->port);

                if ($this->conn->connect_error) {
                    throw new Exception('فشل الاتصال بقاعدة البيانات: ' . $this->conn->connect_error);
                }
            }

            $this->conn->set_charset('utf8mb4');
            $this->initializeSchema();
            return $this->conn;

        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => 'خطأ في الاتصال: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    private function createDatabase() {
        $temp_conn = new mysqli($this->host, $this->user, $this->password, '', $this->port);

        if ($temp_conn->connect_error) {
            throw new Exception('فشل الاتصال المبدئي: ' . $temp_conn->connect_error);
        }

        $sql = "CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!$temp_conn->query($sql)) {
            throw new Exception('فشل إنشاء قاعدة البيانات: ' . $temp_conn->error);
        }

        $temp_conn->close();
    }

    private function initializeSchema() {
        $required_tables = ['craftsmen', 'artisan_portfolio', 'documents', 'grades', 'admins', 'password_resets'];
        $missing_table = false;

        foreach ($required_tables as $table_name) {
            if (!$this->tableExists($table_name)) {
                $missing_table = true;
                break;
            }
        }

        if ($missing_table) {
            $this->runSchemaFile();
        }

        if ($this->tableExists('craftsmen')) {
            $this->ensureCraftsmenColumns();
            $this->ensureCraftsmenFeatureColumns();
        }

        $this->ensureMessagesTable();

        if (!$this->tableExists('contact_messages')) {
            $this->ensureContactMessagesTable();
        }

        // ===== NEW FEATURE TABLES =====
        $this->ensureClientsTable();
        $this->ensureJobRequestsTables();
        $this->ensureProposalsTable();
        $this->ensureComplaintsTable();
        $this->ensureConversationsTable();
        $this->ensureChatMessagesTable();
        $this->ensureNotificationsTable();
    }

    private function runSchemaFile() {
        if (!file_exists($this->schema_file)) {
            return;
        }

        $schema_sql = file_get_contents($this->schema_file);
        if ($schema_sql === false || trim($schema_sql) === '') {
            throw new Exception('تعذر قراءة ملف إنشاء الجداول');
        }

        if (!$this->conn->multi_query($schema_sql)) {
            throw new Exception('فشل إنشاء الجداول: ' . $this->conn->error);
        }

        do {
            $result = $this->conn->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        } while ($this->conn->more_results() && $this->conn->next_result());
    }

    private function tableExists($table_name) {
        $safe_table = $this->conn->real_escape_string($table_name);
        $result = $this->conn->query("SHOW TABLES LIKE '{$safe_table}'");
        return $result && $result->num_rows > 0;
    }

    private function columnExists($table_name, $column_name) {
        $safe_table = $this->conn->real_escape_string($table_name);
        $safe_column = $this->conn->real_escape_string($column_name);
        $result = $this->conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_column}'");
        return $result && $result->num_rows > 0;
    }

    private function ensureCraftsmenColumns() {
        $missing_column_statements = [
            'specialization' => "ALTER TABLE craftsmen ADD COLUMN specialization VARCHAR(80) DEFAULT ''",
            'experience_label' => "ALTER TABLE craftsmen ADD COLUMN experience_label VARCHAR(20) DEFAULT ''",
            'date_of_birth' => "ALTER TABLE craftsmen ADD COLUMN date_of_birth DATE NULL",
            'gender' => "ALTER TABLE craftsmen ADD COLUMN gender ENUM('male', 'female') NULL",
            'grade' => "ALTER TABLE craftsmen ADD COLUMN grade TINYINT NULL",
            'excerpt' => "ALTER TABLE craftsmen ADD COLUMN excerpt TEXT",
            'avatar' => "ALTER TABLE craftsmen ADD COLUMN avatar VARCHAR(255) DEFAULT ''",
            'profile_image' => "ALTER TABLE craftsmen ADD COLUMN profile_image VARCHAR(255) DEFAULT ''",
            'badge_type' => "ALTER TABLE craftsmen ADD COLUMN badge_type VARCHAR(50) DEFAULT NULL",
            'is_featured' => "ALTER TABLE craftsmen ADD COLUMN is_featured TINYINT(1) DEFAULT 0",
            'documents_verified' => "ALTER TABLE craftsmen ADD COLUMN documents_verified TINYINT(1) DEFAULT 0",
            'skills' => "ALTER TABLE craftsmen ADD COLUMN skills TEXT",
            'portfolio_images' => "ALTER TABLE craftsmen ADD COLUMN portfolio_images TEXT",
            'portfolio_videos' => "ALTER TABLE craftsmen ADD COLUMN portfolio_videos TEXT",
            'address' => "ALTER TABLE craftsmen ADD COLUMN address VARCHAR(255) DEFAULT ''",
            'whatsapp' => "ALTER TABLE craftsmen ADD COLUMN whatsapp VARCHAR(30) DEFAULT ''",
            'working_hours' => "ALTER TABLE craftsmen ADD COLUMN working_hours VARCHAR(100) DEFAULT ''",
            'updated_at' => "ALTER TABLE craftsmen ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($missing_column_statements as $column_name => $sql) {
            if (!$this->columnExists('craftsmen', $column_name)) {
                if (!$this->conn->query($sql)) {
                    // Ignore error and continue
                }
            }
        }
    }

    private function ensureMessagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_type ENUM('admin', 'craftsman') NOT NULL,
            sender_id INT NOT NULL,
            receiver_type ENUM('admin', 'craftsman') NOT NULL,
            receiver_id INT NOT NULL,
            subject VARCHAR(200) DEFAULT NULL,
            message_text TEXT NOT NULL,
            attachment_path VARCHAR(255) DEFAULT NULL,
            attachment_name VARCHAR(255) DEFAULT NULL,
            attachment_mime VARCHAR(120) DEFAULT NULL,
            attachment_size INT DEFAULT NULL,
            attachment_type ENUM('image', 'pdf') DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME DEFAULT NULL,
            parent_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_receiver (receiver_type, receiver_id),
            INDEX idx_sender (sender_type, sender_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->conn->query($sql);

        $missing_column_statements = [
            'attachment_path' => "ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL AFTER message_text",
            'attachment_name' => "ALTER TABLE messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path",
            'attachment_mime' => "ALTER TABLE messages ADD COLUMN attachment_mime VARCHAR(120) DEFAULT NULL AFTER attachment_name",
            'attachment_size' => "ALTER TABLE messages ADD COLUMN attachment_size INT DEFAULT NULL AFTER attachment_mime",
            'attachment_type' => "ALTER TABLE messages ADD COLUMN attachment_type ENUM('image', 'pdf') DEFAULT NULL AFTER attachment_size"
        ];

        foreach ($missing_column_statements as $column_name => $alter_sql) {
            if (!$this->columnExists('messages', $column_name)) {
                if (!$this->conn->query($alter_sql)) {
                    // Ignore error and continue
                }
            }
        }
    }

    private function ensureContactMessagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_name VARCHAR(120) NOT NULL,
            sender_email VARCHAR(150) NOT NULL,
            sender_phone VARCHAR(30) NOT NULL,
            sender_type ENUM('guest', 'craftsman', 'client') DEFAULT 'guest',
            subject VARCHAR(200) DEFAULT NULL,
            message_text TEXT NOT NULL,
            status ENUM('new', 'seen', 'replied') DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->conn->query($sql);
    }

    // ==================== FEATURE COLUMNS ====================

    private function ensureCraftsmenFeatureColumns() {
        $extra_columns = [
            'trust_score'    => "ALTER TABLE craftsmen ADD COLUMN trust_score TINYINT UNSIGNED DEFAULT 100",
            'completed_jobs' => "ALTER TABLE craftsmen ADD COLUMN completed_jobs INT DEFAULT 0",
            'reputation_score' => "ALTER TABLE craftsmen ADD COLUMN reputation_score DECIMAL(5,2) DEFAULT 100.00",
        ];
        foreach ($extra_columns as $col => $sql) {
            if (!$this->columnExists('craftsmen', $col)) {
                $this->conn->query($sql);
            }
        }
    }

    // ==================== CLIENTS TABLE ====================

    private function ensureClientsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            phone VARCHAR(30) NOT NULL,
            password VARCHAR(255) NOT NULL,
            city VARCHAR(80) DEFAULT '',
            avatar VARCHAR(255) DEFAULT '',
            status ENUM('active','inactive','banned') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    // ==================== JOB REQUESTS TABLES ====================

    private function ensureJobRequestsTables() {
        $sql1 = "CREATE TABLE IF NOT EXISTS job_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT,
            budget DECIMAL(10,2) DEFAULT NULL,
            urgency ENUM('low','medium','high','urgent') DEFAULT 'medium',
            desired_date DATE DEFAULT NULL,
            city VARCHAR(80) DEFAULT '',
            neighborhood VARCHAR(100) DEFAULT '',
            latitude DECIMAL(10,8) DEFAULT NULL,
            longitude DECIMAL(11,8) DEFAULT NULL,
            contact_preference ENUM('phone','whatsapp','platform') DEFAULT 'platform',
            status ENUM('open','in_progress','completed','canceled') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_jr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            INDEX idx_jr_category (category),
            INDEX idx_jr_status (status),
            INDEX idx_jr_city (city)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql1);

        $sql2 = "CREATE TABLE IF NOT EXISTS job_request_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            photo_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_jrp_request FOREIGN KEY (request_id) REFERENCES job_requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql2);
    }

    // ==================== PROPOSALS TABLE ====================

    private function ensureProposalsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS proposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            artisan_id INT NOT NULL,
            proposed_price DECIMAL(10,2) NOT NULL,
            estimated_duration VARCHAR(100) NOT NULL,
            availability VARCHAR(100) DEFAULT '',
            description TEXT,
            message TEXT,
            status ENUM('pending','accepted','rejected','favorite') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_prop_request FOREIGN KEY (request_id) REFERENCES job_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_prop_artisan FOREIGN KEY (artisan_id) REFERENCES craftsmen(id) ON DELETE CASCADE,
            UNIQUE KEY unique_proposal (request_id, artisan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    // ==================== COMPLAINTS TABLES ====================

    private function ensureComplaintsTable() {
        $sql1 = "CREATE TABLE IF NOT EXISTS complaints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            artisan_id INT NOT NULL,
            complaint_type ENUM('late_work','poor_quality','damaged_property','fraud','no_response','bad_behavior','incomplete_work','payment_dispute','other') NOT NULL,
            description TEXT NOT NULL,
            damage_amount DECIMAL(10,2) DEFAULT NULL,
            incident_date DATE DEFAULT NULL,
            status ENUM('pending','under_review','need_more_info','accepted','rejected','resolved') DEFAULT 'pending',
            admin_notes TEXT,
            penalty_applied VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_comp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_comp_artisan FOREIGN KEY (artisan_id) REFERENCES craftsmen(id) ON DELETE CASCADE,
            INDEX idx_comp_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql1);

        $sql2 = "CREATE TABLE IF NOT EXISTS complaint_evidence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type ENUM('image','video','document') NOT NULL,
            file_name VARCHAR(255) DEFAULT '',
            file_size INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_cev_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql2);
    }

    // ==================== CONVERSATIONS & CHAT TABLES ====================

    private function ensureConversationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            proposal_id INT NOT NULL,
            client_id INT NOT NULL,
            artisan_id INT NOT NULL,
            is_archived_client TINYINT(1) DEFAULT 0,
            is_archived_artisan TINYINT(1) DEFAULT 0,
            is_muted_client TINYINT(1) DEFAULT 0,
            is_muted_artisan TINYINT(1) DEFAULT 0,
            is_reported TINYINT(1) DEFAULT 0,
            last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_conv_request FOREIGN KEY (request_id) REFERENCES job_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_conv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_conv_artisan FOREIGN KEY (artisan_id) REFERENCES craftsmen(id) ON DELETE CASCADE,
            UNIQUE KEY unique_proposal_conv (proposal_id),
            INDEX idx_conv_client (client_id),
            INDEX idx_conv_artisan (artisan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    private function ensureChatMessagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender_type ENUM('client','artisan') NOT NULL,
            sender_id INT NOT NULL,
            message_type ENUM('text','image','video','audio','pdf','location','voice') DEFAULT 'text',
            content TEXT,
            file_path VARCHAR(255) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            latitude DECIMAL(10,8) DEFAULT NULL,
            longitude DECIMAL(11,8) DEFAULT NULL,
            status ENUM('sent','delivered','seen') DEFAULT 'sent',
            delivered_at DATETIME DEFAULT NULL,
            seen_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_cm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            INDEX idx_cm_conv (conversation_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    // ==================== NOTIFICATIONS TABLE ====================

    private function ensureNotificationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_type ENUM('client','artisan','admin') NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(80) NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT,
            link VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_user (user_type, user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }
}

$db = new Database();
$conn = $db->connect();

?>
