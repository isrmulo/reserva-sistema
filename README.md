# 🏢 Sala Reserva Sistema

Sistema completo para gerenciamento de reservas de salas, com **frontend em React** e **backend em PHP**.

## 📂 Estrutura do Projeto

Sala-Reserva-Sistema/
├── frontend/ (React)
│ ├── app/
│ │ ├── page.tsx
│ │ └── api/ (Simulação para desenvolvimento)
│ │ ├── rooms/
│ │ │ └── route.ts
│ │ └── reservations/
│ │ ├── route.ts
│ │ └── [id]/
│ │ └── route.ts
│ ├── components/
│ │ ├── room-list.tsx
│ │ ├── reservation-form.tsx
│ │ └── reservation-list.tsx
│ └── lib/
│ └── types.ts
│
├── backend/ (PHP)
│ ├── db_config.php
│ ├── api/
│ │ ├── rooms.php
│ │ ├── reservations.php
│ │ ├── reservations_delete.php
│ │ └── auth.php
│ └── database.sql

markdown
Copiar
Editar

## 🚀 Tecnologias Utilizadas

- **Frontend:** React, TypeScript
- **Backend:** PHP, MySQL
- **Banco de Dados:** Script SQL incluso (`database.sql`)

---
