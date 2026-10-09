<?php

namespace App\Http\Controllers;

use App\Actions\Installers\CompareProjectQuotes;
use App\Actions\Installers\DescribeInstallerDirectory;
use App\Actions\Installers\DescribeQuoteForClient;
use App\Actions\Installers\RequestInstallerQuote;
use App\Actions\Installers\SaveInstaller;
use App\Actions\Installers\SaveInstallerAccount;
use App\Domain\Installers\QuoteNotPossible;
use App\Http\Requests\InstallerAccountRequest;
use App\Http\Requests\InstallerRequest;
use App\Models\Installer;
use App\Models\Municipality;
use App\Models\QuoteRequest;
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

    /**
     * The quote one installer sent, in detail (ADR-0026). It belongs to the project, so whoever may
     * manage the project may read it; the installer reads their own copy in their inbox.
     */
    public function quote(Request $request, QuoteRequest $quoteRequest, DescribeQuoteForClient $describeQuoteForClient): View
    {
        abort_unless($request->user()->can('manage', $quoteRequest->solarProject), 403);
        // Without a price there is nothing to read: the request is still waiting for an answer.
        abort_if($quoteRequest->installerQuote === null, 404);

        // Contra cuál se compara en el paso 2; sin el parámetro, contra la más barata (ADR-0029).
        return view('installers.quote', $describeQuoteForClient($quoteRequest, $request->integer('vs') ?: null));
    }

    /**
     * Every quote of one project, side by side (ADR-0028). Without two of them there is nothing to
     * compare, and the client stays on the detail page: that is the 404.
     */
    public function compare(Request $request, SolarProject $solarProject, CompareProjectQuotes $compareProjectQuotes): View
    {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        // Which ones the client put side by side; without the parameter, all of them (ADR-0029).
        $chosen = array_values(array_filter(array_map(
            'intval',
            (array) $request->query('cotizaciones', []),
        )));

        $comparison = $compareProjectQuotes($solarProject, $chosen);
        abort_if($comparison === null, 404);

        return view('installers.comparison', $comparison);
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
