# 03 — Autenticación con Laravel Sanctum

## ¿Qué se construyó?

Cuatro endpoints de autenticación usando tokens de Sanctum:

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| POST | `/api/register` | No | Crea un usuario y devuelve sus datos |
| POST | `/api/login` | No | Valida credenciales y devuelve un token |
| GET | `/api/me` | Sí | Devuelve el usuario autenticado |
| POST | `/api/logout` | Sí | Revoca el token actual |

---

## ¿Dónde vive?

```
app/Http/Controllers/Api/AuthController.php    ← orquestación (4 métodos)
app/Services/AuthService.php                   ← lógica: crear usuario, generar token
app/Http/Requests/Api/Auth/RegisterRequest.php ← validación de registro
app/Http/Requests/Api/Auth/LoginRequest.php    ← validación de login
app/Http/Resources/UserResource.php            ← transforma User → JSON seguro
routes/api.php                                 ← rutas registradas
```

---

## ¿Cómo funciona?

### Flujo completo de registro

```
POST /api/register
  │
  ├─► RegisterRequest::rules() ─── valida: name, email único, password confirmed
  │     ↓ si falla → 422 JSON con errores
  │     ↓ si pasa → $request->validated() retorna array limpio
  │
  ├─► AuthService::register(array $data): User
  │     └─ User::create(['password' => Hash::make($data['password']), ...])
  │
  └─► UserResource($user) → JSON sin password ni campos internos
        ↓
      { "status": "success", "data": { "id": 6, "name": "...", ... } }  201
```

### Flujo completo de login

```
POST /api/login
  │
  ├─► LoginRequest valida email y password presentes
  │
  ├─► AuthService::login(email, password): string
  │     ├─ busca el User por email
  │     ├─ Hash::check(password, $user->password)
  │     │     ↓ si falla → ValidationException (422)
  │     └─ $user->createToken('api-token')->plainTextToken
  │
  └─► { "status": "success", "data": { "token": "1|abc123..." } }  200
```

### ¿Qué es un token de Sanctum?

```
1|OaWL2u8QahoBDNN3NwagJOmFEQJQpftf03SORpuR29f0bfef
│  └────────────────────────────────────────────────┘
│              parte aleatoria (40+ chars)
└─ ID del token en personal_access_tokens
```

El token se guarda **hasheado** en la tabla `personal_access_tokens`. Al hacer una request con el header `Authorization: Bearer <token>`, Laravel:
1. Toma la parte del ID (`1`)
2. Busca el registro en la tabla
3. Compara el hash del token recibido contra el guardado
4. Si coincide, autentica al usuario

**Nunca se guarda el token en texto plano**, solo el hash.

### ¿Por qué Sanctum y no JWT?

| | Sanctum (tokens) | JWT |
|---|---|---|
| Revocación inmediata | ✅ (borra la fila de la DB) | ❌ (válido hasta expirar) |
| Instalación | Incluido en Laravel | Paquete externo |
| Almacenamiento | Tabla en tu DB | Stateless (sin DB) |
| Escalabilidad | Requiere DB en cada request | No requiere DB |

Para esta API Sanctum es la elección correcta: podemos revocar tokens con `/logout` y tenemos control total sobre cuántos tokens tiene un usuario.

### El FormRequest como capa de validación

```php
// RegisterRequest.php
public function rules(): array
{
    return [
        'name'     => ['required', 'string', 'max:255'],
        'email'    => ['required', 'email', 'unique:users,email'],  // ← valida unicidad en DB
        'password' => ['required', 'string', 'min:8', 'confirmed'], // ← espera password_confirmation
    ];
}
```

Si las reglas fallan, Laravel automáticamente responde con 422 y la lista de errores — **sin una línea de código extra en el controller**:

```json
{
  "status": "error",
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### El ApiResource como filtro de salida

`UserResource` controla exactamente qué campos salen al cliente:

```php
public function toArray(Request $request): array
{
    return [
        'id'         => $this->id,
        'name'       => $this->name,
        'email'      => $this->email,
        'created_at' => $this->created_at->toDateTimeString(),
        // password, remember_token, two_factor_secret → NUNCA salen
    ];
}
```

Aunque el modelo `User` tenga 15 columnas, el cliente solo ve las 4 que decides exponer.

### La inyección de dependencias en el constructor

```php
class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $authService) {}
```

Laravel resuelve automáticamente `AuthService` desde el contenedor de servicios. No necesitas `new AuthService()`. Ventaja: si mañana `AuthService` necesita una dependencia (un repositorio, un evento), la agregas en su constructor y el sistema la inyecta sin tocar el controller.

---

## Verificación manual

```bash
# Registro
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Ana Lopez","email":"ana@test.com","password":"secret123","password_confirmation":"secret123"}'

# Login → guarda el token
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"ana@test.com","password":"secret123"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

# Perfil con token
curl http://localhost:8000/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# Logout
curl -X POST http://localhost:8000/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# Validación de error (email duplicado)
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Ana Lopez","email":"ana@test.com","password":"secret123","password_confirmation":"secret123"}'
```

---

## Glosario

| Término | Significado |
|---|---|
| **Sanctum** | Paquete oficial de Laravel para autenticación API con tokens o cookies |
| **Bearer token** | Token enviado en el header `Authorization: Bearer <token>` |
| **`HasApiTokens`** | Trait en el modelo User que agrega `createToken()` y `tokens()` |
| **`plainTextToken`** | El token completo que se le da al cliente (solo se ve una vez) |
| **`auth:sanctum`** | Middleware que verifica el Bearer token en cada request |
| **FormRequest** | Clase que encapsula las reglas de validación, devuelve 422 automáticamente si fallan |
| **`validated()`** | Método de FormRequest que retorna solo los campos que pasaron las reglas |
| **Inyección de dependencias** | Laravel crea e inyecta instancias automáticamente desde el contenedor de servicios |
