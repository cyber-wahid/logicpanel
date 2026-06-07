<?php

declare(strict_types=1);

namespace LogicPanel\Infrastructure\Dns;

use LogicPanel\Domain\Dns\DnsDomain;
use LogicPanel\Domain\Dns\DnsRecord;
use LogicPanel\Domain\Service\Service;

class CoreDnsService
{
    private string $zonesDir;

    public function __construct()
    {
        // Path to the zones directory where CoreDNS will read from
        $baseDir = dirname(__DIR__, 3); // Moves up to logicpanel root
        $this->zonesDir = $baseDir . '/storage/dns/zones';

        if (!is_dir($this->zonesDir)) {
            if (!mkdir($this->zonesDir, 0755, true) && !is_dir($this->zonesDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->zonesDir));
            }
        }
    }

    /**
     * Synchronize all user domains from their Services into DNS Zones.
     * This ensures the DNS Manager always reflects their apps.
     */
    public function syncUserDomains(int $userId): void
    {
        // 1. Fetch all services for the user to collect active domains
        $services = Service::where('user_id', $userId)->get();
        $activeDomains = [];
        
        foreach ($services as $service) {
            if (empty($service->domain)) continue;
            
            $domains = explode(',', $service->domain);
            foreach ($domains as $d) {
                $d = strtolower(trim($d));
                if (!empty($d) && preg_match('/^[a-zA-Z0-9.-]+$/', $d)) {
                    $activeDomains[] = $d;
                }
            }
        }
        
        $activeDomains = array_unique($activeDomains);
        
        // 2. Fetch current DNS domains in the database
        $existingDnsDomains = DnsDomain::where('user_id', $userId)->get();
        $existingDomainNames = $existingDnsDomains->pluck('domain_name')->toArray();
        
        // 3. Find domains to ADD and REMOVE
        $toAdd = array_diff($activeDomains, $existingDomainNames);
        $toRemove = array_diff($existingDomainNames, $activeDomains);
        
        // 4. Remove old domains
        foreach ($toRemove as $domainName) {
            $domain = $existingDnsDomains->where('domain_name', $domainName)->first();
            if ($domain) {
                $this->deleteZoneFile($domainName);
                $domain->delete();
            }
        }
        
        // 5. Add new domains
        $serverIp = $_ENV['SERVER_IP'] ?? $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()) ?: '127.0.0.1';
        
        foreach ($toAdd as $domainName) {
            $domain = DnsDomain::create([
                'user_id' => $userId,
                'domain_name' => $domainName,
                'status' => 'active'
            ]);
            
            // Add default A record (@ -> Server IP)
            DnsRecord::create([
                'domain_id' => $domain->id,
                'type' => 'A',
                'name' => '@',
                'content' => $serverIp,
                'ttl' => 3600,
                'prio' => 0
            ]);
            
            // Add default CNAME record (www -> @)
            DnsRecord::create([
                'domain_id' => $domain->id,
                'type' => 'CNAME',
                'name' => 'www',
                'content' => '@',
                'ttl' => 3600,
                'prio' => 0
            ]);
            
            // Generate initial zone file
            $this->generateZoneFile($domain);
        }
    }

    /**
     * Generate the RFC1035 Zone file for a specific domain.
     */
    public function generateZoneFile(DnsDomain $domain): void
    {
        // Load records
        $records = $domain->records()->get();

        $domainName = rtrim($domain->domain_name, '.') . '.';
        
        // Define default nameservers from env or fallback
        $ns1 = $_ENV['DNS_NS1'] ?? 'ns1.hostcreed.com.';
        $ns2 = $_ENV['DNS_NS2'] ?? 'ns2.hostcreed.com.';

        // Ensure NS ends with a dot
        $ns1 = rtrim($ns1, '.') . '.';
        $ns2 = rtrim($ns2, '.') . '.';

        $adminEmail = 'admin.' . $domainName;

        $serial = date('YmdH'); // Format YYYYMMDDHH

        $zone = "; Zone file for {$domain->domain_name}\n";
        $zone .= "\$ORIGIN {$domainName}\n";
        $zone .= "\$TTL 3600\n\n";

        // SOA Record
        $zone .= "@   IN  SOA {$ns1} {$adminEmail} (\n";
        $zone .= "            {$serial} ; serial\n";
        $zone .= "            7200       ; refresh (2 hours)\n";
        $zone .= "            3600       ; retry (1 hour)\n";
        $zone .= "            1209600    ; expire (2 weeks)\n";
        $zone .= "            3600       ; minimum (1 hour)\n";
        $zone .= "            )\n\n";

        // Write custom records
        $hasNs = false;
        foreach ($records as $record) {
            $name = $record->name === '@' ? '' : $record->name;
            $ttl = $record->ttl ?: 3600;
            $type = strtoupper($record->type);
            $content = $record->content;

            if ($type === 'NS' && empty($name)) {
                $hasNs = true;
            }

            // Ensure CNAME, MX, NS, SRV content ends with a dot if it's a domain name
            if (in_array($type, ['CNAME', 'MX', 'NS', 'SRV'])) {
                if (!filter_var($content, FILTER_VALIDATE_IP)) {
                    $content = rtrim($content, '.') . '.';
                }
            }

            if ($type === 'MX') {
                $prio = $record->prio ?: 10;
                $zone .= "{$name}\tIN\t{$type}\t{$prio}\t{$content}\n";
            } elseif ($type === 'TXT') {
                // Ensure TXT content is quoted
                if (!str_starts_with($content, '"')) {
                    $content = '"' . $content . '"';
                }
                $zone .= "{$name}\tIN\t{$type}\t{$content}\n";
            } else {
                $zone .= "{$name}\tIN\t{$type}\t{$content}\n";
            }
        }

        // Add default NS records if none were explicitly defined for the apex domain
        if (!$hasNs) {
            $zone .= "\n; Default Nameservers\n";
            $zone .= "@\tIN\tNS\t{$ns1}\n";
            $zone .= "@\tIN\tNS\t{$ns2}\n";
        }

        $filePath = $this->zonesDir . '/' . $domain->domain_name . '.db';
        file_put_contents($filePath, $zone);
    }

    /**
     * Delete the zone file for a domain.
     */
    public function deleteZoneFile(string $domainName): void
    {
        $filePath = $this->zonesDir . '/' . $domainName . '.db';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
