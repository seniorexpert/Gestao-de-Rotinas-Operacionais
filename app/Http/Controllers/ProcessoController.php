<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessoController extends Controller
{
    public function __construct(private ConsultaRotinas $consultaRotinas) {}

    public function index(Request $request): View
    {
        return view('processos.index', $this->consultaRotinas->catalogos($request->user()));
    }

    public function gestaoRotinas(Request $request): View
    {
        return view('processos.gestao-rotinas', [
            ...$this->consultaRotinas->catalogos($request->user()),
            'ferramentas' => $this->consultaRotinas->ferramentasParaCadastro($request->user()),
        ]);
    }

    public function usuariosPorUnidade(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'id_empresa' => ['required_without:unit', 'nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:255'],
        ]);
        $usuario = $request->user();
        $empresas = DB::table('empresas')->whereIn('id_empresa', $this->consultaRotinas->empresas($usuario));
        $idEmpresa = isset($dados['id_empresa'])
            ? $empresas->where('id_empresa', $dados['id_empresa'])->value('id_empresa')
            : $empresas->where('empresa', $dados['unit'])->value('id_empresa');

        abort_unless($idEmpresa, 404);
        $request->attributes->set(AuditoriaService::UNIDADE_HTTP, (int) $idEmpresa);

        return response()->json($this->consultaRotinas->colaboradores($usuario)
            ->whereIn('u.id_usuario', DB::table('empresa_usuario')->select('id_usuario')->where('id_empresa', $idEmpresa))
            ->select('u.id_usuario', 'u.usuario')
            ->get());
    }
}
