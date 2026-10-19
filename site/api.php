<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

try {
    $pdo = db();
    $action = (string)($_GET['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($action === 'status' && $method === 'GET') {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        json_response([
            'setup_required' => $count === 0,
            'authenticated' => current_user_id() !== null,
            'username' => $_SESSION['username'] ?? null,
            'csrf' => csrf_token(),
            'app_name' => APP_NAME
        ]);
    }

    if ($action === 'setup') {
        require_method('POST');
        verify_csrf();
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            json_response(['error' => 'A conta inicial já foi criada. Entre com seu login.'], 409);
        }
        $data = request_data();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) {
            json_response(['error' => 'Use um usuário de 3 a 40 caracteres: letras, números, ponto, hífen ou sublinhado.'], 422);
        }
        if (strlen($password) < 10) json_response(['error' => 'A senha deve ter pelo menos 10 caracteres.'], 422);
        if (strlen($password) > 200) json_response(['error' => 'A senha é muito longa.'], 422);

        $stmt = $pdo->prepare('INSERT INTO users(username,password_hash) VALUES(?,?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $defaults = [
            ['Salário','income','#16a34a'], ['Renda extra','income','#22c55e'],
            ['Alimentação','expense','#f97316'], ['Moradia','expense','#ef4444'],
            ['Transporte','expense','#eab308'], ['Lazer','expense','#8b5cf6'],
            ['Saúde','expense','#06b6d4'], ['Educação','expense','#3b82f6'],
            ['Compras','expense','#ec4899'], ['Outros','expense','#64748b'],
        ];
        $insert = $pdo->prepare('INSERT INTO categories(user_id,name,kind,color) VALUES(?,?,?,?)');
        foreach ($defaults as $cat) $insert->execute([$userId, ...$cat]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        json_response(['ok' => true, 'username' => $username]);
    }

    if ($action === 'login') {
        require_method('POST');
        verify_csrf();
        $data = request_data();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(250000);
            json_response(['error' => 'Usuário ou senha incorretos.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        json_response(['ok' => true, 'username' => $user['username']]);
    }

    if ($action === 'logout') {
        require_method('POST');
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_response(['ok' => true]);
    }

    if ($action === 'backup' && $method === 'GET') {
        $userId = require_auth();
        $payload = ['format' => 'meu-financeiro-backup', 'version' => 1, 'exported_at' => date(DATE_ATOM), 'data' => []];
        foreach (['categories','transactions','goals','budgets'] as $table) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE user_id = ? ORDER BY id");
            $stmt->execute([$userId]);
            $payload['data'][$table] = $stmt->fetchAll();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="backup-financeiro-' . date('Y-m-d-His') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'export_csv' && $method === 'GET') {
        $userId = require_auth();
        $stmt = $pdo->prepare("SELECT t.transaction_date,t.description,t.kind,t.amount_cents,c.name AS category,t.payment_method,t.notes
            FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
            WHERE t.user_id=? ORDER BY t.transaction_date DESC,t.id DESC");
        $stmt->execute([$userId]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lancamentos-financeiros-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data','Descrição','Tipo','Valor (R$)','Categoria','Forma de pagamento','Observações'], ';');
        while ($row = $stmt->fetch()) {
            fputcsv($out, [$row['transaction_date'], $row['description'], $row['kind'] === 'income' ? 'Entrada' : 'Saída',
                number_format(((int)$row['amount_cents'])/100, 2, ',', '.'), $row['category'] ?? '',
                $row['payment_method'], $row['notes']], ';');
        }
        fclose($out);
        exit;
    }

    $userId = require_auth();

    if ($method !== 'GET') verify_csrf();

    switch ($action) {
        case 'summary':
            $month = (string)($_GET['month'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) json_response(['error'=>'Mês inválido.'],422);
            $start = $month . '-01';
            $end = date('Y-m-d', strtotime($start . ' +1 month'));
            $stmt = $pdo->prepare("SELECT kind, SUM(amount_cents) total FROM transactions WHERE user_id=? AND transaction_date>=? AND transaction_date<? GROUP BY kind");
            $stmt->execute([$userId,$start,$end]);
            $totals = ['income'=>0,'expense'=>0];
            foreach ($stmt->fetchAll() as $row) $totals[$row['kind']] = (int)$row['total'];
            $stmt = $pdo->prepare("SELECT c.id,c.name,c.color,COALESCE(SUM(t.amount_cents),0) total
                FROM categories c LEFT JOIN transactions t ON t.category_id=c.id AND t.user_id=c.user_id AND t.kind='expense' AND t.transaction_date>=? AND t.transaction_date<?
                WHERE c.user_id=? AND c.kind IN ('expense','both') GROUP BY c.id ORDER BY total DESC");
            $stmt->execute([$start,$end,$userId]);
            $byCategory = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT t.*,c.name category_name,c.color category_color FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
                WHERE t.user_id=? AND t.transaction_date>=? AND t.transaction_date<? ORDER BY t.transaction_date DESC,t.id DESC LIMIT 8");
            $stmt->execute([$userId,$start,$end]);
            $recent = $stmt->fetchAll();
            $stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY id DESC');
            $stmt->execute([$userId]);
            json_response(['month'=>$month,'income'=>$totals['income'],'expense'=>$totals['expense'],'balance'=>$totals['income']-$totals['expense'],
                'categories'=>$byCategory,'recent'=>$recent,'goals'=>$stmt->fetchAll()]);
        case 'transactions':
            $month = trim((string)($_GET['month'] ?? ''));
            $category = (int)($_GET['category'] ?? 0);
            $where = ['t.user_id=?']; $params = [$userId];
            if ($month !== '' && preg_match('/^\d{4}-\d{2}$/',$month)) {
                $where[] = 't.transaction_date>=? AND t.transaction_date<?';
                $params[] = $month.'-01'; $params[] = date('Y-m-d',strtotime($month.'-01 +1 month'));
            }
            if ($category > 0) { $where[]='t.category_id=?'; $params[]=$category; }
            $stmt=$pdo->prepare('SELECT t.*,c.name category_name,c.color category_color FROM transactions t LEFT JOIN categories c ON c.id=t.category_id WHERE '.implode(' AND ',$where).' ORDER BY t.transaction_date DESC,t.id DESC LIMIT 1000');
            $stmt->execute($params);
            json_response(['transactions'=>$stmt->fetchAll()]);
        case 'save_transaction':
            require_method('POST');
            $d=request_data();
            $id=(int)($d['id']??0);
            $description=trim((string)($d['description']??''));
            $kind=(string)($d['kind']??'');
            $date=(string)($d['transaction_date']??'');
            $categoryId=(int)($d['category_id']??0);
            if ($description==='' || mb_strlen($description)>180) json_response(['error'=>'Informe uma descrição de até 180 caracteres.'],422);
            if (!in_array($kind,['income','expense'],true)) json_response(['error'=>'Tipo de lançamento inválido.'],422);
            if (!valid_date($date)) json_response(['error'=>'Informe uma data válida.'],422);
            try { $cents=cents_from_value($d['amount']??null); } catch (Throwable $e) { json_response(['error'=>$e->getMessage()],422); }
            if ($categoryId && !owned_category($pdo,$categoryId,$userId)) json_response(['error'=>'Categoria inválida.'],422);
            $payment=mb_substr(trim((string)($d['payment_method']??'')),0,80);
            $notes=mb_substr(trim((string)($d['notes']??'')),0,1000);
            if ($id>0) {
                $stmt=$pdo->prepare('UPDATE transactions SET category_id=?,description=?,amount_cents=?,kind=?,transaction_date=?,payment_method=?,notes=? WHERE id=? AND user_id=?');
                $stmt->execute([$categoryId?:null,$description,$cents,$kind,$date,$payment,$notes,$id,$userId]);
                if (!$stmt->rowCount()) {
                    $check=$pdo->prepare('SELECT id FROM transactions WHERE id=? AND user_id=?'); $check->execute([$id,$userId]);
                    if (!$check->fetch()) json_response(['error'=>'Lançamento não encontrado.'],404);
                }
            } else {
                $stmt=$pdo->prepare('INSERT INTO transactions(user_id,category_id,description,amount_cents,kind,transaction_date,payment_method,notes) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->execute([$userId,$categoryId?:null,$description,$cents,$kind,$date,$payment,$notes]);
                $id=(int)$pdo->lastInsertId();
            }
            json_response(['ok'=>true,'id'=>$id]);
        case 'delete_transaction':
            require_method('POST');
            $d=request_data(); $id=(int)($d['id']??0);
            $stmt=$pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?'); $stmt->execute([$id,$userId]);
            json_response(['ok'=>true]);
        case 'categories':
            $stmt=$pdo->prepare('SELECT * FROM categories WHERE user_id=? ORDER BY kind,name'); $stmt->execute([$userId]);
            json_response(['categories'=>$stmt->fetchAll()]);
        case 'save_category':
            require_method('POST');
            $d=request_data(); $name=trim((string)($d['name']??'')); $kind=(string)($d['kind']??'expense');
            $color=(string)($d['color']??'#64748b');
            if ($name==='' || mb_strlen($name)>60) json_response(['error'=>'Informe um nome de categoria de até 60 caracteres.'],422);
            if (!in_array($kind,['income','expense','both'],true)) json_response(['error'=>'Tipo inválido.'],422);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/',$color)) $color='#64748b';
            try { $stmt=$pdo->prepare('INSERT INTO categories(user_id,name,kind,color) VALUES(?,?,?,?)'); $stmt->execute([$userId,$name,$kind,$color]); }
            catch (PDOException $e) { json_response(['error'=>'Já existe uma categoria com esse nome e tipo.'],409); }
            json_response(['ok'=>true]);
        case 'delete_category':
            require_method('POST');
            $d=request_data(); $id=(int)($d['id']??0);
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id=? AND category_id=?'); $stmt->execute([$userId,$id]);
            if ((int)$stmt->fetchColumn()>0) json_response(['error'=>'Essa categoria tem lançamentos. Altere a categoria dos lançamentos antes de removê-la.'],409);
            $stmt=$pdo->prepare('DELETE FROM categories WHERE id=? AND user_id=?'); $stmt->execute([$id,$userId]);
            json_response(['ok'=>true]);
        case 'goals':
            $stmt=$pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY id DESC'); $stmt->execute([$userId]);
            json_response(['goals'=>$stmt->fetchAll()]);
        case 'save_goal':
            require_method('POST');
            $d=request_data(); $id=(int)($d['id']??0); $name=trim((string)($d['name']??''));
            if ($name==='' || mb_strlen($name)>100) json_response(['error'=>'Informe um nome de meta de até 100 caracteres.'],422);
            try { $target=cents_from_value($d['target']??null); } catch (Throwable $e) { json_response(['error'=>$e->getMessage()],422); }
            $savedValue=(float)($d['saved']??0);
            if (!is_finite($savedValue) || $savedValue<0 || $savedValue>999999999.99) json_response(['error'=>'Valor guardado inválido.'],422);
            $saved=(int)round($savedValue*100); $date=(string)($d['target_date']??'');
            if ($date!=='' && !valid_date($date)) json_response(['error'=>'Data da meta inválida.'],422);
            $notes=mb_substr(trim((string)($d['notes']??'')),0,1000);
            if ($id>0) {
                $stmt=$pdo->prepare('UPDATE goals SET name=?,target_cents=?,saved_cents=?,target_date=?,notes=? WHERE id=? AND user_id=?');
                $stmt->execute([$name,$target,$saved,$date?:null,$notes,$id,$userId]);
            } else {
                $stmt=$pdo->prepare('INSERT INTO goals(user_id,name,target_cents,saved_cents,target_date,notes) VALUES(?,?,?,?,?,?)');
                $stmt->execute([$userId,$name,$target,$saved,$date?:null,$notes]);
            }
            json_response(['ok'=>true]);
        case 'delete_goal':
            require_method('POST');
            $d=request_data(); $stmt=$pdo->prepare('DELETE FROM goals WHERE id=? AND user_id=?'); $stmt->execute([(int)($d['id']??0),$userId]);
            json_response(['ok'=>true]);
        case 'budgets':
            $stmt=$pdo->prepare("SELECT b.*,c.name category_name,c.color category_color FROM budgets b JOIN categories c ON c.id=b.category_id WHERE b.user_id=? ORDER BY b.month DESC,c.name");
            $stmt->execute([$userId]); json_response(['budgets'=>$stmt->fetchAll()]);
        case 'save_budget':
            require_method('POST');
            $d=request_data(); $cat=(int)($d['category_id']??0); $month=(string)($d['month']??'');
            if (!owned_category($pdo,$cat,$userId)) json_response(['error'=>'Escolha uma categoria válida.'],422);
            if (!preg_match('/^\d{4}-\d{2}$/',$month)) json_response(['error'=>'Mês inválido.'],422);
            try { $limit=cents_from_value($d['limit']??null); } catch (Throwable $e) { json_response(['error'=>$e->getMessage()],422); }
            $stmt=$pdo->prepare('INSERT INTO budgets(user_id,category_id,month,limit_cents) VALUES(?,?,?,?) ON CONFLICT(user_id,category_id,month) DO UPDATE SET limit_cents=excluded.limit_cents');
            $stmt->execute([$userId,$cat,$month,$limit]); json_response(['ok'=>true]);
        case 'delete_budget':
            require_method('POST');
            $d=request_data(); $stmt=$pdo->prepare('DELETE FROM budgets WHERE id=? AND user_id=?'); $stmt->execute([(int)($d['id']??0),$userId]);
            json_response(['ok'=>true]);
        case 'restore':
            require_method('POST');
            $d=request_data();
            if (($d['format']??'')!=='meu-financeiro-backup' || (int)($d['version']??0)!==1 || !is_array($d['data']??null)) {
                json_response(['error'=>'O arquivo não parece ser um backup válido deste sistema.'],422);
            }
            $required=['categories','transactions','goals','budgets'];
            foreach ($required as $table) if (!is_array($d['data'][$table]??null)) json_response(['error'=>'O backup está incompleto: '.$table.'.'],422);
            if (count($d['data']['transactions'])>50000) json_response(['error'=>'O backup excede o limite de 50.000 lançamentos.'],413);
            $pdo->beginTransaction();
            try {
                foreach (['transactions','budgets','goals','categories'] as $table) {
                    $del=$pdo->prepare("DELETE FROM {$table} WHERE user_id=?"); $del->execute([$userId]);
                }
                $catMap=[];
                $ins=$pdo->prepare('INSERT INTO categories(user_id,name,kind,color) VALUES(?,?,?,?)');
                foreach ($d['data']['categories'] as $r) {
                    $name=trim((string)($r['name']??'')); $kind=(string)($r['kind']??'expense'); $color=(string)($r['color']??'#64748b');
                    if ($name==='' || !in_array($kind,['income','expense','both'],true)) throw new RuntimeException('Categoria inválida no backup.');
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/',$color)) $color='#64748b';
                    $ins->execute([$userId,mb_substr($name,0,60),$kind,$color]); $catMap[(string)($r['id']??'')] = (int)$pdo->lastInsertId();
                }
                $ins=$pdo->prepare('INSERT INTO transactions(user_id,category_id,description,amount_cents,kind,transaction_date,payment_method,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?)');
                foreach ($d['data']['transactions'] as $r) {
                    $kind=(string)($r['kind']??''); $date=(string)($r['transaction_date']??''); $amount=(int)($r['amount_cents']??0);
                    if (!in_array($kind,['income','expense'],true) || !valid_date($date) || $amount<=0) throw new RuntimeException('Lançamento inválido no backup.');
                    $oldCat=(string)($r['category_id']??''); $newCat=$catMap[$oldCat]??null;
                    $ins->execute([$userId,$newCat,mb_substr(trim((string)($r['description']??'Lançamento')),0,180),$amount,$kind,$date,
                        mb_substr((string)($r['payment_method']??''),0,80),mb_substr((string)($r['notes']??''),0,1000),(string)($r['created_at']??date('Y-m-d H:i:s'))]);
                }
                $ins=$pdo->prepare('INSERT INTO goals(user_id,name,target_cents,saved_cents,target_date,notes,created_at) VALUES(?,?,?,?,?,?,?)');
                foreach ($d['data']['goals'] as $r) {
                    $target=(int)($r['target_cents']??0); $saved=(int)($r['saved_cents']??0);
                    if ($target<=0 || $saved<0) throw new RuntimeException('Meta inválida no backup.');
                    $date=(string)($r['target_date']??''); if ($date!=='' && !valid_date($date)) $date=null;
                    $ins->execute([$userId,mb_substr((string)($r['name']??'Meta'),0,100),$target,$saved,$date?:null,
                        mb_substr((string)($r['notes']??''),0,1000),(string)($r['created_at']??date('Y-m-d H:i:s'))]);
                }
                $ins=$pdo->prepare('INSERT INTO budgets(user_id,category_id,month,limit_cents,created_at) VALUES(?,?,?,?,?)');
                foreach ($d['data']['budgets'] as $r) {
                    $oldCat=(string)($r['category_id']??''); $newCat=$catMap[$oldCat]??null;
                    $month=(string)($r['month']??''); $limit=(int)($r['limit_cents']??0);
                    if (!$newCat || !preg_match('/^\d{4}-\d{2}$/',$month) || $limit<=0) continue;
                    $ins->execute([$userId,$newCat,$month,$limit,(string)($r['created_at']??date('Y-m-d H:i:s'))]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_response(['ok'=>true]);
        default:
            json_response(['error'=>'Operação não encontrada.'],404);
    }
} catch (Throwable $e) {
    error_log('[Meu Financeiro] ' . $e->getMessage());
    json_response(['error'=>'Não foi possível concluir a operação. Confira a configuração do banco e tente novamente.'],500);
}
