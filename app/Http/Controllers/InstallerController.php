<?php

namespace App\Http\Controllers;

use App\Actions\Installers\DescribeInstallerDirectory;
use App\Actions\Installers\RequestInstallerQuote;
use App\Actions\Installers\SaveInstaller;
use App\Actions\Installers\SaveInstallerAccount;
use App\Domain\Installers\QuoteNotPossible;
use App\Http\Requests\InstallerAccountRequest;
use App\Http\Requests\InstallerRequest;
use App\Models\Installer;
use App\Models\Municipality;
use App\Models\SolarProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Allied installers (ADR-0022): the directory a client reads to ask for a quote, and the screens
 * where an administrator keeps it. The installer is a record, not a user: there is no role for it yet.
 */
class InstallerController extends Controller
{
    public function index(Request $request, DescribeInstallerDirectory $describeInstallerDirectory): View
    {
        $projectId = $request->integer('proyecto') ?: null;

        return view('installers.index', $describeInstallerDirectory($request->user(), $projectId));
    }

    public function requestQuote(Request $request, Installer $installer, RequestInstallerQuote $requestInstallerQuote): RedirectResponse
    {
        $validated = $request->validate([
            'solar_project_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $solarProject = SolarProject::query()->findOrFail($validated['solar_project_id']);
        abort_unless($request->user()->can('manage', $solarProject), 403);

        try {
            $requestInstallerQuote($solarProject, $installer, $validated['note'] ?? null);
        } catch (QuoteNotPossible $exception) {
            return back()->withErrors(['installer' => $exception->getMessage()]);
        }

        return redirect()
            ->route('installers.index', ['proyecto' => $solarProject->id])
            ->with('status', "Le pedimos cotización a {$installer->name}: sus datos de contacto ya están en su tarjeta.");
    }

    public function create(): View
    {
        return view('installers.form', [
            'installer' => null,
            'municipalities' => $this->municipalities(),
            'covered' => [],
        ]);
    }

    public function store(InstallerRequest $request, SaveInstaller $saveInstaller): RedirectResponse
    {
        $installer = $saveInstaller($request->validated());

        return redirect()
            ->route('installers.index')
            ->with('status', "Se agregó {$installer->name}: ya aparece para los clientes de los municipios que cubre.");
    }

    /** The login the installer answers its requests with (ADR-0023). */
    public function account(InstallerAccountRequest $request, Installer $installer, SaveInstallerAccount $saveInstallerAccount): RedirectResponse
    {
        $account = $saveInstallerAccount($installer, $request->validated());

        return redirect()
            ->route('installers.edit', $installer)
            ->with('status', "{$installer->name} ya entra con el usuario {$account->username}.");
    }

    public function edit(Installer $installer): View
    {
        return view('installers.form', [
            'installer' => $installer->load('account'),
            'municipalities' => $this->municipalities(),
            'covered' => $installer->municipalities()->pluck('municipalities.id')->all(),
        ]);
    }

    public function update(InstallerRequest $request, Installer $installer, SaveInstaller $saveInstaller): RedirectResponse
    {
        $installer = $saveInstaller($request->validated(), $installer);

        return redirect()
            ->route('installers.index')
            ->with('status', $installer->active
                ? "Se actualizó {$installer->name}."
                : "{$installer->name} quedó oculto: ya no se ofrece a los clientes, pero sus solicitudes se conservan.");
    }

    /**
     * @return Collection<int, Municipality>
     */
    private function municipalities()
    {
        return Municipality::query()->active()->orderBy('name')->get(['id', 'name', 'zone']);
    }
}
