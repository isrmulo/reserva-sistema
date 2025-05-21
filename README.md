# 🏢 Sala Reserva Sistema

Sistema completo para gerenciamento de reservas de salas, com **frontend em React** e **backend em PHP**.

---

## 📂 Estrutura do Projeto

```txt
Sala-Reserva-Sistema/
  frontend/ (React)
    app/
      page.tsx
      api/ (Simulação para desenvolvimento)
        rooms/
          route.ts
        reservations/
          route.ts
          [id]/
            route.ts
    components/
      room-list.tsx
      reservation-form.tsx
      reservation-list.tsx
    lib/
      types.ts

  backend/ (PHP)
    db_config.php
    api/
      rooms.php
      reservations.php
      reservations_delete.php
      auth.php
    database.sql
```

---

## 🚀 Tecnologias Utilizadas

- **Frontend:** React (Next.js), TypeScript
- **Backend:** PHP
- **Banco de Dados:** MySQL
- **Outros:** TailwindCSS (se usado), Axios, Fetch API, etc.

---

## ✅ Requisitos

### Backend (PHP + MySQL)
- PHP 7.4+
- Servidor Apache ou Nginx
- MySQL/MariaDB
- Extensão `mysqli` habilitada

### Frontend (React)
- Node.js 16+
- NPM ou Yarn

---

## ⚙️ Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/sala-reserva-sistema.git
cd sala-reserva-sistema
```

---

### 2. Configurar o Backend (PHP)

- Importe o arquivo `backend/database.sql` em seu banco de dados MySQL.
- Edite o `backend/db_config.php` com suas credenciais:

```php
$host = "localhost";
$user = "seu_usuario";
$password = "sua_senha";
$dbname = "nome_do_banco";
```

- Coloque a pasta `backend/` em um servidor local (ex: `htdocs/` do XAMPP ou `www/` no WAMP).

---

### 3. Rodar o Frontend (React)

```bash
cd frontend
npm install
npm run dev
```

Acesse o sistema em: `http://localhost:3000`

---

## ▶️ Como Usar

- Visualize as salas disponíveis.
- Faça uma reserva escolhendo data e horário.
- Liste e cancele reservas.

---

## 📌 Observações

- O diretório `app/api/` simula respostas para desenvolvimento local.
- Para conectar ao backend real, direcione chamadas ao `localhost/backend/api/...`.

---

## 📃 Licença

Este projeto é de uso educacional e livre para aprimoramento.
