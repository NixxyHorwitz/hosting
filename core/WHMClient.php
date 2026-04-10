<?php
// require_once __DIR__ . '/../config/database.php'; // Removed because we will inject variables dynamically

class WHMClient
{
    private $host;
    private $username;
    private $token;

    public function __construct($host = null, $username = null, $token = null)
    {
        if (empty($host) || empty($username) || empty($token)) {
            throw new Exception("Kredensial WHM Server wajib disertakan saat memanggil WHMClient baru.");
        }
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->token = $token;
    }

    private function request($endpoint, $params = [])
    {
        $url = $this->host . ":2087/json-api/" . $endpoint . "?" . http_build_query($params);

        $ch = curl_init();

        curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            "Authorization: whm {$this->username}:{$this->token}"
        ]
    ]);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception("WHM Connection Error: " . curl_error($ch));
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (!$result) {
            throw new Exception("Invalid WHM response");
        }

        // ✅ HANDLE ERROR WHM
        if (isset($result['metadata']) && $result['metadata']['result'] == 0) {
            throw new Exception("WHM Error: " . $result['metadata']['reason']);
        }

        return $result;
    }

    // =========================
    // CLIENT AREA DATA
    // =========================

    public function getClientStats($username)
    {
        $account = $this->getAccount($username);
        $bandwidth = $this->getBandwidth($username);

        if (!isset($account['data']['acct'][0])) {
            throw new Exception("Account not found");
        }

        $acct = $account['data']['acct'][0];

        $diskUsed = (float) ($acct['diskused'] ?? 0);
        $diskLimitRaw = $acct['disklimit'] ?? 'unlimited';
        $diskLimit = ($diskLimitRaw === 'unlimited') ? 0 : (float) $diskLimitRaw;

        $bwUsed = 0;
        $bwLimitRaw = $acct['maxbw'] ?? 'unlimited';
        $bwLimit = ($bwLimitRaw === 'unlimited') ? 0 : (float) $bwLimitRaw;

        if (isset($bandwidth['data']['acct'][0]['totalbytes'])) {
            $bwUsed = $bandwidth['data']['acct'][0]['totalbytes'] / (1024 * 1024);
        }

        return [
            'domain' => $acct['domain'],
            'username' => $acct['user'],
            'package' => $acct['plan'],
            'status' => $acct['suspended'] ? 'Suspended' : 'Active',
            'ip' => $acct['ip'],

            'disk' => [
                'used' => $diskUsed,
                'limit' => $diskLimit == 0 ? 'Unlimited' : $diskLimit,
                'percent' => $this->calculatePercent($diskUsed, $diskLimit)
            ],

            'bandwidth' => [
                'used' => round($bwUsed, 2),
                'limit' => $bwLimit == 0 ? 'Unlimited' : $bwLimit,
                'percent' => $this->calculatePercent($bwUsed, $bwLimit)
            ]
        ];
    }

    private function calculatePercent($used, $limit)
    {
        if ($limit == 0) return 0;
        return round(($used / $limit) * 100, 2);
    }


public function createCpanelSession($username, $app = '')
{
    $params = [
        'user'        => $username,
        'service'     => 'cpaneld',
        'api.version' => 1
    ];

    // Jika ada request aplikasi spesifik (seperti Database_phpMyAdmin)
    if (!empty($app)) {
        $params['app'] = $app;
    }

    return $this->request('create_user_session', $params);
}

public function createWebmailSession($username)
{
    // Tambahkan 'api.version' => 1
    return $this->request('create_user_session', [
        'user'        => $username,
        'service'     => 'webmaild',
        'api.version' => 1
    ]);
}

    // =========================
    // ACCOUNT
    // =========================

    public function createAccount($data)
    {
        return $this->request('createacct', $data);
    }

    public function terminateAccount($username)
    {
        return $this->request('removeacct', ['user' => $username]);
    }

    public function suspendAccount($username, $reason = 'Suspended')
    {
        return $this->request('suspendacct', [
            'user' => $username,
            'reason' => $reason
        ]);
    }

    public function unsuspendAccount($username)
    {
        return $this->request('unsuspendacct', ['user' => $username]);
    }

    public function changePassword($username, $password)
    {
        return $this->request('passwd', [
            'user' => $username,
            'pass' => $password
        ]);
    }

    public function getAccount($username)
    {
        return $this->request('accountsummary', [
            'user' => $username,
            'api.version' => 1 
        ]);
    }

    public function listAccounts()
    {
        return $this->request('listaccts');
    }

    // =========================
    // PACKAGE
    // =========================

    public function listPackages()
    {
        return $this->request('listpkgs');
    }

    public function changePackage($username, $package)
    {
        return $this->request('changepackage', [
            'user' => $username,
            'pkg' => $package
        ]);
    }

    // =========================
    // BANDWIDTH
    // =========================

    public function getBandwidth($username)
    {
        return $this->request('showbw', [
            'searchtype' => 'user',
            'search' => $username
        ]);
    }

    public function setQuota($username, $quota)
    {
        return $this->request('setquota', [
            'user' => $username,
            'quota' => $quota
        ]);
    }

    // =========================
    // DNS
    // =========================

    public function listDNS($domain)
    {
        return $this->request('dumpzone', ['domain' => $domain]);
    }

    public function addDNSRecord($domain, $name, $type, $value, $ttl = 14400)
    {
        return $this->request('addzonerecord', [
            'domain' => $domain,
            'name' => $name,
            'type' => $type,
            'address' => $value,
            'ttl' => $ttl
        ]);
    }
}