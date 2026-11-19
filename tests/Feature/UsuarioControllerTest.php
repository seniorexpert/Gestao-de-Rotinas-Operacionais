<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsuarioControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_gestao_exibe_os_usuarios_grupos_e_unidades_do_banco(): void
    {
        $gerente = $this->gerente();
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Enfermagem']);
        $unidade = Empresa::create(['empresa' => 'Unidade Jardim']);
        $usuario = $this->usuario('Ana Silva', 'ana-silva', $grupo);
        $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);

        $resposta = $this->actingAs($gerente)->get(route('usuarios.index'));

        $resposta->assertOk()
            ->assertSeeText(['Ana Silva', 'ana-silva', 'Grupo de Acesso', 'Unidade Jardim', 'Enfermagem'])
            ->assertSee('name="photo"', false)
            ->assertDontSee(['name="group"', 'name="profile"'], false);
        $this->assertSame(1, substr_count($resposta->getContent(), 'name="id_grupo_acesso"'));
    }

    public function test_cadastro_persiste_nome_grupo_e_unidades_com_os_campos_do_banco(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Enfermagem']);
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);
        $senha = 'senha-de-cadastro';

        $this->actingAs($gerente)->post(route('users.store'), [
            'usuario' => 'Lucas Exemplo',
            'username' => 'lucas-exemplo',
            'password' => $senha,
            'id_grupo_acesso' => (string) $grupo->getKey(),
            'empresas' => [(string) $centro->getKey(), (string) $norte->getKey()],
            'photo' => $this->foto(),
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasNoErrors();

        $usuario = User::where('username', 'lucas-exemplo')->firstOrFail();
        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $usuario->getKey(),
            'usuario' => 'Lucas Exemplo',
            'id_grupo_acesso' => $grupo->getKey(),
            'grupo_acesso_nome' => 'Enfermagem',
            'status' => true,
        ]);
        $this->assertTrue(Hash::check($senha, $usuario->password));
        $this->assertStringStartsWith('users/', $usuario->photo);
        Storage::disk('public')->assertExists($usuario->photo);
        $this->assertEqualsCanonicalizing([$centro->getKey(), $norte->getKey()], $usuario->empresas->modelKeys());
        $this->assertDatabaseHas('empresa_usuario', [
            'id_usuario' => $usuario->getKey(),
            'id_empresa' => $norte->getKey(),
            'empresa_nome' => 'Unidade Norte',
        ]);
        $auditoria = DB::table('logs_auditoria')->where('tabela', 'usuarios')
            ->where('registro_id', (string) $usuario->getKey())->first();
        $this->assertSame($gerente->getKey(), $auditoria->id_usuario);
        $this->assertArrayNotHasKey('password', json_decode($auditoria->dados_novos, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_cadastro_valida_nome_obrigatorio_e_ids_de_grupo_e_unidade(): void
    {
        $gerente = $this->gerente();

        $this->actingAs($gerente)->from(route('usuarios.index'))->post(route('users.store'), [
            'username' => 'registro-invalido',
            'password' => 'senha-de-teste',
            'id_grupo_acesso' => 999,
            'empresas' => [999],
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasErrors([
            'usuario' => 'Informe o nome completo.',
            'id_grupo_acesso' => 'Selecione um grupo de acesso cadastrado.',
            'empresas.0' => 'Selecione unidades cadastradas.',
        ]);

        $this->assertDatabaseCount('usuarios', 1);
        $this->assertDatabaseEmpty('empresa_usuario');
    }

    public function test_cadastro_valida_login_duplicado_em_usuarios(): void
    {
        $gerente = $this->gerente();

        $this->actingAs($gerente)->from(route('usuarios.index'))->post(route('users.store'), [
            'usuario' => 'Outro Usuário',
            'username' => $gerente->username,
            'password' => 'senha-de-teste',
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasErrors([
            'username' => 'Já existe um usuário com esse login.',
        ])->assertSessionMissingInput('password');

        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_edicao_atualiza_grupo_e_unidades_e_preserva_login_senha_e_status(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();
        $usuario = $this->usuario('Lucas Exemplo', 'lucas-exemplo', status: false);
        $foto = $this->foto()->store('users', 'public');
        $usuario->update(['photo' => $foto]);
        $senhaOriginal = $usuario->password;
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Coordenação']);
        $centro = Empresa::create(['empresa' => 'Unidade Centro']);
        $norte = Empresa::create(['empresa' => 'Unidade Norte']);
        $sul = Empresa::create(['empresa' => 'Unidade Sul']);
        $usuario->empresas()->attach([
            $centro->getKey() => ['empresa_nome' => $centro->empresa],
            $norte->getKey() => ['empresa_nome' => $norte->empresa],
        ]);

        $this->actingAs($gerente)->put(route('users.update', $usuario->getKey()), [
            'usuario' => 'Lucas Atualizado',
            'username' => 'login-diferente',
            'password' => '',
            'status' => true,
            'id_grupo_acesso' => $grupo->getKey(),
            'empresas' => [$centro->getKey(), $sul->getKey()],
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $usuario->getKey(),
            'usuario' => 'Lucas Atualizado',
            'username' => 'lucas-exemplo',
            'password' => $senhaOriginal,
            'photo' => $foto,
            'status' => false,
            'id_grupo_acesso' => $grupo->getKey(),
            'grupo_acesso_nome' => 'Coordenação',
        ]);
        Storage::disk('public')->assertExists($foto);
        $this->assertEqualsCanonicalizing([$centro->getKey(), $sul->getKey()], $usuario->empresas()->get()->modelKeys());
    }

    public function test_edicao_pode_limpar_grupo_e_unidades_e_atualizar_a_senha(): void
    {
        $gerente = $this->gerente();
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Farmácia']);
        $usuario = $this->usuario('Ana Silva', 'ana-silva', $grupo);
        $unidade = Empresa::create(['empresa' => 'Unidade Centro']);
        $usuario->empresas()->attach($unidade->getKey(), ['empresa_nome' => $unidade->empresa]);

        $this->actingAs($gerente)->put(route('users.update', $usuario->getKey()), [
            'usuario' => $usuario->usuario,
            'id_grupo_acesso' => '',
            'empresas' => '',
            'password' => 'senha-atualizada',
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $usuario->getKey(),
            'id_grupo_acesso' => null,
            'grupo_acesso_nome' => null,
        ]);
        $this->assertDatabaseEmpty('empresa_usuario');
        $this->assertTrue(Hash::check('senha-atualizada', $usuario->refresh()->password));
    }

    public function test_edicao_substitui_a_foto_e_exclui_o_arquivo_anterior(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();
        $usuario = $this->usuario('Ana Silva', 'ana-silva');
        $fotoAnterior = $this->foto()->store('users', 'public');
        $usuario->update(['photo' => $fotoAnterior]);

        $this->actingAs($gerente)->put(route('users.update', $usuario->getKey()), [
            'usuario' => $usuario->usuario,
            'photo' => $this->foto(),
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasNoErrors();

        $novaFoto = $usuario->refresh()->photo;
        $this->assertStringStartsWith('users/', $novaFoto);
        $this->assertNotSame($fotoAnterior, $novaFoto);
        Storage::disk('public')->assertExists($novaFoto)->assertMissing($fotoAnterior);
    }

    public function test_edicao_remove_a_foto_do_banco_e_do_armazenamento(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();
        $usuario = $this->usuario('Ana Silva', 'ana-silva');
        $foto = $this->foto()->store('users', 'public');
        $usuario->update(['photo' => $foto]);

        $this->actingAs($gerente)->put(route('users.update', $usuario->getKey()), [
            'usuario' => $usuario->usuario,
            'remove_photo' => '1',
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasNoErrors();

        $this->assertNull($usuario->refresh()->photo);
        Storage::disk('public')->assertMissing($foto);
    }

    public function test_cadastro_recusa_arquivo_que_nao_e_imagem(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();

        $this->actingAs($gerente)->from(route('usuarios.index'))->post(route('users.store'), [
            'usuario' => 'Ana Silva',
            'username' => 'ana-silva',
            'password' => 'senha-de-teste',
            'photo' => UploadedFile::fake()->createWithContent('foto.txt', 'arquivo de texto'),
        ])->assertRedirectToRoute('usuarios.index')->assertSessionHasErrors([
            'photo' => 'Selecione um arquivo de imagem para a foto.',
        ]);

        $this->assertDatabaseMissing('usuarios', ['username' => 'ana-silva']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_edicao_descarta_o_upload_se_o_usuario_nao_existe(): void
    {
        Storage::fake('public');
        $gerente = $this->gerente();

        $this->actingAs($gerente)->put(route('users.update', 999), [
            'usuario' => 'Ana Silva',
            'photo' => $this->foto(),
        ])->assertNotFound();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_colaborador_nao_pode_alterar_grupo_de_acesso(): void
    {
        $gerente = $this->gerente();
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Cuidador']);
        $usuario = $this->usuario('Ana Silva', 'ana-silva', $grupo);

        $this->actingAs($usuario)->put(route('users.update', $usuario->getKey()), [
            'usuario' => $usuario->usuario,
            'id_grupo_acesso' => $gerente->id_grupo_acesso,
        ])->assertForbidden();

        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $usuario->getKey(),
            'id_grupo_acesso' => $grupo->getKey(),
        ]);
    }

    private function gerente(): User
    {
        $grupo = GrupoAcesso::create(['grupo_acesso' => 'Gerente Geral', 'status' => true]);

        return $this->usuario('Gestora Exemplo', 'gestora-exemplo', $grupo);
    }

    private function usuario(string $nome, string $login, ?GrupoAcesso $grupo = null, bool $status = true): User
    {
        return User::create([
            'usuario' => $nome,
            'username' => $login,
            'password' => 'senha-de-teste',
            'id_grupo_acesso' => $grupo?->getKey(),
            'grupo_acesso_nome' => $grupo?->grupo_acesso,
            'status' => $status,
        ]);
    }

    private function foto(): UploadedFile
    {
        $conteudo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGNQcYr4D8IMMAYAOVgG9X6cVUcAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('foto.png', $conteudo);
    }
}
