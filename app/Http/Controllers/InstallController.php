<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use Illuminate\Http\Request;
use Throwable;

class InstallController extends Controller
{
    public function __construct(private InstallerService $installer) {}

    public function show()
    {
        return view('install.index', [
            'requirements' => $this->installer->requirements(),
            'requirementsPassed' => $this->installer->requirementsPassed(),
        ]);
    }

    public function testConnection(Request $request)
    {
        $db = $this->validateDatabase($request);

        $result = $this->installer->testConnection($db);

        return back()->with(
            $result['ok'] ? 'success' : 'install_error',
            $result['message']
        );
    }

    public function run(Request $request)
    {
        if (! $this->installer->requirementsPassed()) {
            return back()->with('install_error', 'Des prérequis ne sont pas satisfaits.');
        }

        $db = $this->validateDatabase($request);
        $db['app_url'] = $request->validate(['app_url' => ['required', 'url', 'max:255']])['app_url'];

        $accounts = $request->validate([
            'super_name' => ['required', 'max:255'],
            'super_email' => ['required', 'email', 'max:255'],
            'super_password' => ['required', 'min:8', 'max:255'],
            'org_name' => ['required', 'max:255'],
            'admin_name' => ['required', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'min:8', 'max:255'],
        ]);

        try {
            $result = $this->installer->install(
                $db,
                ['name' => $accounts['super_name'], 'email' => $accounts['super_email'], 'password' => $accounts['super_password']],
                [
                    'name' => $accounts['org_name'],
                    'admin_name' => $accounts['admin_name'],
                    'admin_email' => $accounts['admin_email'],
                    'admin_password' => $accounts['admin_password'],
                ],
                $request->boolean('demo_accounts')
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('install_error', 'Installation interrompue : '.$e->getMessage());
        }

        return redirect()->route('install.done')->with('install_accounts', $result['accounts']);
    }

    public function done()
    {
        if (! $this->installer->isInstalled()) {
            return redirect()->route('install.show');
        }

        return view('install.done', [
            'accounts' => session('install_accounts', []),
        ]);
    }

    private function validateDatabase(Request $request): array
    {
        $data = $request->validate([
            'db_driver' => ['required', 'in:mysql,sqlite'],
            'db_host' => ['nullable', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'max:255'],
            'db_username' => ['nullable', 'max:255'],
            'db_password' => ['nullable', 'max:255'],
        ]);

        if ($data['db_driver'] === 'mysql' && empty($data['db_host'])) {
            $data['db_host'] = '127.0.0.1';
        }

        return [
            'driver' => $data['db_driver'],
            'host' => $data['db_host'] ?? null,
            'port' => $data['db_port'] ?? null,
            'database' => $data['db_database'],
            'username' => $data['db_username'] ?? null,
            'password' => $data['db_password'] ?? null,
        ];
    }
}
