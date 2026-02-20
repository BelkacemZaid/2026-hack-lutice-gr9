<?php

/**
 * CONFIGURATION (Issue de ton script Python)
 */
$SECRET_KEY = "1792e7eb-dcb9-4836-9d36-21e2ff0d8334"; // X-Auth-Token
$PROJECT_ID = "d6f12197-9fea-4d34-9dce-99929ada13de";
$ZONE       = "fr-par-1";

/**
 * PARAMÈTRES (Issus de ton script Bash)
 */
$clientName = $argv[1] ?? "demo-client";
$offerType  = $argv[2] ?? "formule1";
$serverName = "lutice-$clientName";
$homeDir = getenv('HOME') ?: getenv('USERPROFILE');
$sshKeyPath = $homeDir . "/.ssh/id_ed25519.pub";

if (!file_exists($sshKeyPath)) {
    die("Erreur: Clé publique introuvable dans $sshKeyPath\n");
}
$sshPubKey = trim(file_get_contents($sshKeyPath));

/**
 * FONCTION API NATIVE (remplace requests en Python)
 */
function callSCW($method, $path, $payload = null) {
    global $SECRET_KEY, $ZONE;

    // Si le chemin ne commence pas par http, on construit l'URL
    $url = (strpos($path, 'http') === 0) ? $path : "https://api.scaleway.com/instance/v1/zones/$ZONE$path";

    $ch = curl_init($url);
    $headers = [
        "X-Auth-Token: $SECRET_KEY",
        "Content-Type: application/json"
    ];

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($payload) {
        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Erreur cURL: $curlError");
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        $errorMsg = $data['message'] ?? ($data['error'] ?? $response);
        $details = '';
        if (isset($data['fields'])) {
            $details = ' Fields: ' . json_encode($data['fields']);
        }
        if (isset($payload)) {
            $details .= ' Payload: ' . json_encode($payload);
        }
        throw new Exception("Erreur API SCW [$httpCode] $path: $errorMsg$details");
    }

    return $data;
}

try {
    echo "[1/5] Configuration du Security Group ($serverName)...\n";
    $sgList = callSCW('GET', "/security_groups")['security_groups'];
    $sgId = null;
    foreach ($sgList as $sg) {
        if ($sg['name'] === $serverName) { $sgId = $sg['id']; break; }
    }

    if (!$sgId) {
        $sgRes = callSCW('POST', "/security_groups", [
            "name" => $serverName,
            "project" => $PROJECT_ID,
            "inbound_default_policy" => "drop",
            "stateful" => true
        ]);
        $sgId = $sgRes['security_group']['id'];
    }

    $rules = callSCW('GET', "/security_groups/$sgId/rules")['rules'];
    foreach ([22, 80, 443] as $port) {
        $found = false;
        foreach ($rules as $r) {
            if ($r['direction'] === 'inbound' && ($r['dest_port_from'] ?? 0) == $port) $found = true;
        }
        if (!$found) {
            callSCW('POST', "/security_groups/$sgId/rules", [
                "action" => "accept",
                "direction" => "inbound",
                "ip_range" => "0.0.0.0/0",
                "protocol" => "TCP",
                "dest_port_from" => $port,
                "dest_port_to" => $port
            ]);
        }
    }

    echo "[2/5] Recherche d'une IP publique...\n";
    $ips = callSCW('GET', "/ips")['ips'];
    $ipId = null; $publicIp = "";
    foreach ($ips as $ip) {
        if (empty($ip['server'])) {
            $ipId = $ip['id'];
            $publicIp = $ip['address'];
            echo "  -> Réutilisation IP: $publicIp\n";
            break;
        }
    }
    if (!$ipId) {
        $newIp = callSCW('POST', "/ips", ["project" => $PROJECT_ID]);
        $ipId = $newIp['ip']['id'];
        $publicIp = $newIp['ip']['address'];
    }

    echo "[3/6] Recherche de l'image Debian 12...\n";
    $images = callSCW('GET', "/images?arch=x86_64&name=debian_bookworm")['images'];
    $imageId = null;
    foreach ($images as $img) {
        if (stripos($img['name'], 'debian') !== false && stripos($img['name'], 'bookworm') !== false) {
            $imageId = $img['id'];
            echo "  -> Image trouvée: {$img['name']} ({$imageId})\n";
            break;
        }
    }
    if (!$imageId) {
        $images = callSCW('GET', "/images?arch=x86_64")['images'];
        foreach ($images as $img) {
            if (stripos($img['name'], 'debian') !== false) {
                $imageId = $img['id'];
                echo "  -> Image trouvée (fallback): {$img['name']} ({$imageId})\n";
                break;
            }
        }
    }
    if (!$imageId) {
        throw new Exception("Aucune image Debian trouvée");
    }

    $cloudConfig = "#cloud-config\n" .
        "users:\n" .
        "  - name: deploy\n" .
        "    groups: [sudo]\n" .
        "    shell: /bin/bash\n" .
        "    sudo: ['ALL=(ALL) NOPASSWD:ALL']\n" .
        "    ssh_authorized_keys:\n" .
        "      - $sshPubKey\n" .
        "runcmd:\n" .
        "  - sed -i 's/^#\\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config\n" .
        "  - systemctl restart ssh";

    echo "[4/6] Création de l'instance...\n";


    $serverData = [
        "name"            => $serverName,
        "project"         => $PROJECT_ID,
        "commercial_type" => "DEV1-S",
        "image"           => "debian_bookworm",
        "security_group"  => $sgId,
        "enable_ipv6"     => true,
        "boot_type"       => "local"
    ];

    $serverData["public_ip"] = $ipId;

    $srvInfo = callSCW('POST', "/servers", $serverData);
    $serverId = $srvInfo['server']['id'];

   // echo "Réponse création serveur:\n";
    //echo json_encode($srvInfo, JSON_PRETTY_PRINT) . "\n\n";

    echo "⚡ [5/5] Démarrage du serveur...\n";
    callSCW('POST', "/servers/$serverId/action", ["action" => "poweron"]);

    echo "Attente de 10 secondes...\n";
    sleep(10);

    echo "Récupération des infos du serveur...\n";
    $serverDetails = callSCW('GET', "/servers/$serverId");
    $serverInfo = $serverDetails['server'];

    $publicIp = "";
    if (isset($serverInfo['public_ip']['address'])) {
        $publicIp = $serverInfo['public_ip']['address'];
    } elseif (isset($serverInfo['ip'])) {
        $publicIp = $serverInfo['ip'];
    }

    echo "\n✅ DEPLOIEMENT RÉUSSI !\n";
    echo "ID: $serverId\n";
    echo "IP: $publicIp\n";
    echo "SSH: ssh deploy@$publicIp\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
