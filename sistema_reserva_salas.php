<?php
/**
 * Sistema de Reserva de Salas - Arquivo Único
 * 
 * Este arquivo contém tanto o frontend (HTML/JS/CSS) quanto o backend (PHP/MySQL)
 * Para usar, basta fazer upload deste arquivo para um servidor PHP com MySQL
 * e acessá-lo pelo navegador.
 */

// Verificar se é uma requisição de API
$isApiRequest = false;

// Verificar se a URL contém /api/ ou se é uma requisição AJAX
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
    (isset($_GET['api']) && $_GET['api'] === 'true')) {
    $isApiRequest = true;
}

// Se for uma requisição de API, processar como backend
if ($isApiRequest) {
    // Configurações iniciais da API
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

    // Se for uma requisição OPTIONS, retornar apenas os cabeçalhos
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Configurações do banco de dados
    $host = "localhost";
    $dbname = "room_reservation";
    $username = "root";
    $password = ""; // Altere para sua senha

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die(json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]));
    }

    // Obter o endpoint da URL
    $endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

    // Roteamento básico baseado no endpoint
    switch ($endpoint) {
        case 'rooms':
            handleRoomsRequest($pdo);
            break;
        case 'reservations':
            handleReservationsRequest($pdo);
            break;
        case 'auth':
            handleAuthRequest($pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint não encontrado']);
            break;
    }
    
    // Encerrar a execução após processar a API
    exit;
}

// Funções do backend
function handleRoomsRequest($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // Consulta para obter todas as salas
            $stmt = $pdo->prepare("SELECT * FROM rooms ORDER BY name");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            
            // Para cada sala, obter suas características
            $result = [];
            foreach ($rooms as $room) {
                $featuresStmt = $pdo->prepare("SELECT feature_name FROM room_features WHERE room_id = ?");
                $featuresStmt->execute([$room['id']]);
                $features = $featuresStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $room['features'] = $features;
                $result[] = $room;
            }
            
            echo json_encode($result);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar salas: ' . $e->getMessage()]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Obter dados do corpo da requisição
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || !isset($data['location']) || !isset($data['capacity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados incompletos']);
                exit;
            }
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Inserir sala
            $stmt = $pdo->prepare("INSERT INTO rooms (name, location, capacity) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['location'], $data['capacity']]);
            
            $roomId = $pdo->lastInsertId();
            
            // Inserir características da sala
            if (isset($data['features']) && is_array($data['features'])) {
                $featureStmt = $pdo->prepare("INSERT INTO room_features (room_id, feature_name) VALUES (?, ?)");
                foreach ($data['features'] as $feature) {
                    $featureStmt->execute([$roomId, $feature]);
                }
            }
            
            // Confirmar transação
            $pdo->commit();
            
            // Retornar a sala criada
            $data['id'] = $roomId;
            echo json_encode($data);
            
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar sala: ' . $e->getMessage()]);
        }
    }
}

function handleReservationsRequest($pdo) {
    // Verificar se há um ID na URL (para DELETE)
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;
            
            if ($userId) {
                // Consulta para obter reservas de um usuário específico
                $stmt = $pdo->prepare("
                    SELECT * FROM reservations 
                    WHERE user_id = ? 
                    ORDER BY date, start_time
                ");
                $stmt->execute([$userId]);
            } else {
                // Consulta para obter todas as reservas
                $stmt = $pdo->prepare("
                    SELECT * FROM reservations 
                    ORDER BY date, start_time
                ");
                $stmt->execute();
            }
            
            $reservations = $stmt->fetchAll();
            echo json_encode($reservations);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar reservas: ' . $e->getMessage()]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Obter dados do corpo da requisição
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar dados obrigatórios
            if (!isset($data['roomId']) || !isset($data['title']) || 
                !isset($data['date']) || !isset($data['startTime']) || !isset($data['endTime'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados incompletos']);
                exit;
            }
            
            // Definir userId padrão se não fornecido
            if (!isset($data['userId'])) {
                $data['userId'] = 1; // Usuário padrão para demonstração
            }
            
            // Verificar disponibilidade da sala
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservations 
                WHERE room_id = ? AND date = ? AND 
                ((start_time <= ? AND end_time > ?) OR 
                 (start_time < ? AND end_time >= ?) OR 
                 (start_time >= ? AND end_time <= ?))
            ");
            
            $stmt->execute([
                $data['roomId'], 
                $data['date'], 
                $data['startTime'], $data['startTime'],
                $data['endTime'], $data['endTime'],
                $data['startTime'], $data['endTime']
            ]);
            
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['error' => 'Sala já reservada para este horário']);
                exit;
            }
            
            // Inserir reserva
            $stmt = $pdo->prepare("
                INSERT INTO reservations 
                (room_id, user_id, title, description, date, start_time, end_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $description = isset($data['description']) ? $data['description'] : null;
            
            $stmt->execute([
                $data['roomId'],
                $data['userId'],
                $data['title'],
                $description,
                $data['date'],
                $data['startTime'],
                $data['endTime']
            ]);
            
            $reservationId = $pdo->lastInsertId();
            
            // Retornar a reserva criada
            $data['id'] = $reservationId;
            echo json_encode($data);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar reserva: ' . $e->getMessage()]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $id) {
        try {
            // Verificar se a reserva existe
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            $reservation = $stmt->fetch();
            
            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['error' => 'Reserva não encontrada']);
                exit;
            }
            
            // Excluir a reserva
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Reserva excluída com sucesso']);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir reserva: ' . $e->getMessage()]);
        }
    }
}

function handleAuthRequest($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obter dados do corpo da requisição
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar se os dados necessários foram fornecidos
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha são obrigatórios']);
            exit;
        }
        
        try {
            // Buscar usuário pelo email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            // Verificar se o usuário existe e a senha está correta
            if (!$user || !password_verify($data['password'], $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Email ou senha incorretos']);
                exit;
            }
            
            // Remover a senha do objeto de usuário antes de retornar
            unset($user['password']);
            
            // Gerar um token simples (em produção, use JWT ou similar)
            $token = bin2hex(random_bytes(32));
            
            // Retornar dados do usuário e token
            echo json_encode([
                'user' => $user,
                'token' => $token
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao fazer login: ' . $e->getMessage()]);
        }
    }
}

// Se não for uma requisição de API, servir o frontend
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Reserva de Salas</title>
  <!-- Tailwind CSS via CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- React via CDN -->
  <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
  <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
  <!-- Babel para JSX -->
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <!-- Date-fns para formatação de datas -->
  <script src="https://cdn.jsdelivr.net/npm/date-fns@2.30.0/index.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/date-fns@2.30.0/locale/pt-BR/index.js"></script>
  <style>
    /* Estilos básicos */
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      line-height: 1.5;
      color: #333;
    }
    .container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1rem;
    }
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border-width: 0;
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen">
  <div id="root"></div>

  <!-- Script principal da aplicação -->
  <script type="text/babel">
    // Definição dos tipos (apenas para referência, o JavaScript não usa tipos em runtime)
    /**
     * @typedef {Object} Room
     * @property {number} id
     * @property {string} name
     * @property {string} location
     * @property {number} capacity
     * @property {string[]} features
     */

    /**
     * @typedef {Object} Reservation
     * @property {number} id
     * @property {number} roomId
     * @property {number} userId
     * @property {string} title
     * @property {string} [description]
     * @property {string} date
     * @property {string} startTime
     * @property {string} endTime
     */

    // Configuração da API
    const API_URL = window.location.pathname + "?api=true&endpoint="; // Aponta para este mesmo arquivo

    // Utilitários
    const formatDate = (dateStr) => {
      const date = new Date(dateStr);
      return date.toLocaleDateString("pt-BR", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    };

    // Componentes UI Reutilizáveis
    const Card = ({ children, className = "" }) => {
      return <div className={`bg-white rounded-lg shadow-md overflow-hidden ${className}`}>{children}</div>;
    };

    const CardHeader = ({ children, className = "" }) => {
      return <div className={`px-4 py-3 border-b ${className}`}>{children}</div>;
    };

    const CardContent = ({ children, className = "" }) => {
      return <div className={`px-4 py-4 ${className}`}>{children}</div>;
    };

    const CardFooter = ({ children, className = "" }) => {
      return <div className={`px-4 py-3 bg-gray-50 ${className}`}>{children}</div>;
    };

    const Button = ({ children, onClick, className = "", variant = "primary", disabled = false, type = "button" }) => {
      const baseClasses =
        "px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2";

      const variants = {
        primary: "bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500",
        secondary: "bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-500",
        destructive: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-500",
      };

      const classes = `${baseClasses} ${variants[variant]} ${disabled ? "opacity-50 cursor-not-allowed" : ""} ${className}`;

      return (
        <button type={type} onClick={onClick} className={classes} disabled={disabled}>
          {children}
        </button>
      );
    };

    const Badge = ({ children, className = "", variant = "default" }) => {
      const variants = {
        default: "bg-gray-100 text-gray-800",
        outline: "bg-transparent border border-gray-300 text-gray-700",
      };

      return (
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${variants[variant]} ${className}`}
        >
          {children}
        </span>
      );
    };

    const Input = ({ id, label, type = "text", value, onChange, placeholder = "", required = false, className = "" }) => {
      return (
        <div className="space-y-1">
          {label && (
            <label htmlFor={id} className="block text-sm font-medium text-gray-700">
              {label}
            </label>
          )}
          <input
            type={type}
            id={id}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            required={required}
            className={`block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${className}`}
          />
        </div>
      );
    };

    const Select = ({
      id,
      label,
      value,
      onChange,
      options,
      placeholder = "Selecione...",
      required = false,
      className = "",
    }) => {
      return (
        <div className="space-y-1">
          {label && (
            <label htmlFor={id} className="block text-sm font-medium text-gray-700">
              {label}
            </label>
          )}
          <select
            id={id}
            value={value}
            onChange={onChange}
            required={required}
            className={`block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${className}`}
          >
            <option value="" disabled>
              {placeholder}
            </option>
            {options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      );
    };

    const Tabs = ({ children, value, onChange }) => {
      const [activeTab, setActiveTab] = React.useState(value);

      React.useEffect(() => {
        setActiveTab(value);
      }, [value]);

      const handleTabChange = (tabValue) => {
        setActiveTab(tabValue);
        if (onChange) {
          onChange(tabValue);
        }
      };

      // Filtrar e clonar os filhos para adicionar a prop isActive
      const tabs = React.Children.map(children, (child) => {
        if (child.type === TabsList || child.type === TabsContent) {
          return React.cloneElement(child, {
            activeTab,
            onTabChange: handleTabChange,
          });
        }
        return child;
      });

      return <div className="w-full">{tabs}</div>;
    };

    const TabsList = ({ children, activeTab, onTabChange }) => {
      // Filtrar e clonar os filhos para adicionar as props necessárias
      const triggers = React.Children.map(children, (child) => {
        if (child.type === TabsTrigger) {
          return React.cloneElement(child, {
            isActive: activeTab === child.props.value,
            onSelect: () => onTabChange(child.props.value),
          });
        }
        return child;
      });

      return <div className="flex space-x-1 rounded-lg bg-gray-200 p-1 mb-4">{triggers}</div>;
    };

    const TabsTrigger = ({ children, value, isActive, onSelect }) => {
      return (
        <button
          onClick={onSelect}
          className={`flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors ${
            isActive ? "bg-white shadow text-gray-900" : "text-gray-700 hover:text-gray-900 hover:bg-gray-100"
          }`}
        >
          {children}
        </button>
      );
    };

    const TabsContent = ({ children, value, activeTab }) => {
      if (value !== activeTab) {
        return null;
      }

      return <div>{children}</div>;
    };

    // Componentes principais da aplicação
    const RoomList = ({ rooms, onSelectRoom }) => {
      const [searchTerm, setSearchTerm] = React.useState("");

      const filteredRooms = rooms.filter(
        (room) =>
          room.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          room.location.toLowerCase().includes(searchTerm.toLowerCase()),
      );

      return (
        <div className="space-y-4">
          <div className="relative">
            <input
              type="text"
              placeholder="Buscar salas..."
              className="w-full p-2 border rounded-md"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>

          {filteredRooms.length === 0 ? (
            <p className="text-center py-8">Nenhuma sala encontrada.</p>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredRooms.map((room) => (
                <Card key={room.id}>
                  <CardHeader>
                    <h3 className="text-lg font-medium">{room.name}</h3>
                    <p className="text-sm text-gray-500">Localização: {room.location}</p>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2">
                      <p>Capacidade: {room.capacity} pessoas</p>
                      <div className="flex flex-wrap gap-2">
                        {room.features.map((feature, index) => (
                          <Badge key={index} variant="outline">
                            {feature}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  </CardContent>
                  <CardFooter>
                    <Button onClick={() => onSelectRoom(room)} className="w-full">
                      Selecionar Sala
                    </Button>
                  </CardFooter>
                </Card>
              ))}
            </div>
          )}
        </div>
      );
    };

    const ReservationForm = ({ rooms, selectedRoom, onCreateReservation }) => {
      const [roomId, setRoomId] = React.useState(selectedRoom ? selectedRoom.id.toString() : "");
      const [date, setDate] = React.useState(() => {
        const today = new Date();
        return today.toISOString().split("T")[0];
      });
      const [startTime, setStartTime] = React.useState("09:00");
      const [endTime, setEndTime] = React.useState("10:00");
      const [title, setTitle] = React.useState("");
      const [description, setDescription] = React.useState("");

      // Atualizar roomId quando selectedRoom mudar
      React.useEffect(() => {
        if (selectedRoom) {
          setRoomId(selectedRoom.id.toString());
        }
      }, [selectedRoom]);

      const handleSubmit = (e) => {
        e.preventDefault();

        if (!roomId || !date || !startTime || !endTime || !title) {
          alert("Por favor, preencha todos os campos obrigatórios.");
          return;
        }

        const newReservation = {
          roomId: Number.parseInt(roomId),
          title,
          description,
          date,
          startTime,
          endTime,
          userId: 1, // Em um sistema real, este seria o ID do usuário logado
        };

        onCreateReservation(newReservation);

        // Limpar formulário
        setTitle("");
        setDescription("");
        setStartTime("09:00");
        setEndTime("10:00");
      };

      return (
        <Card>
          <CardHeader>
            <h2 className="text-xl font-bold">Fazer Reserva</h2>
            <p className="text-sm text-gray-500">Preencha os detalhes para reservar uma sala</p>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <Select
                id="room"
                label="Sala"
                value={roomId}
                onChange={(e) => setRoomId(e.target.value)}
                options={rooms.map((room) => ({
                  value: room.id.toString(),
                  label: `${room.name} - ${room.location}`,
                }))}
                placeholder="Selecione uma sala"
                required
              />

              <Input
                id="title"
                label="Título da Reserva"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Ex: Reunião de Equipe"
                required
              />

              <Input
                id="description"
                label="Descrição (opcional)"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Detalhes adicionais sobre a reserva"
              />

              <Input id="date" label="Data" type="date" value={date} onChange={(e) => setDate(e.target.value)} required />

              <div className="grid grid-cols-2 gap-4">
                <Input
                  id="startTime"
                  label="Hora de Início"
                  type="time"
                  value={startTime}
                  onChange={(e) => setStartTime(e.target.value)}
                  required
                />
                <Input
                  id="endTime"
                  label="Hora de Término"
                  type="time"
                  value={endTime}
                  onChange={(e) => setEndTime(e.target.value)}
                  required
                />
              </div>

              <Button type="submit" className="w-full mt-2">
                Confirmar Reserva
              </Button>
            </form>
          </CardContent>
        </Card>
      );
    };

    const ReservationList = ({ reservations, rooms }) => {
      // Função para encontrar o nome da sala pelo ID
      const getRoomName = (roomId) => {
        const room = rooms.find((r) => r.id === roomId);
        return room ? room.name : "Sala não encontrada";
      };

      // Ordenar reservas por data e hora
      const sortedReservations = [...reservations].sort((a, b) => {
        const dateA = new Date(`${a.date}T${a.startTime}`);
        const dateB = new Date(`${b.date}T${b.startTime}`);
        return dateA.getTime() - dateB.getTime();
      });

      // Agrupar reservas por data
      const reservationsByDate = {};

      sortedReservations.forEach((reservation) => {
        if (!reservationsByDate[reservation.date]) {
          reservationsByDate[reservation.date] = [];
        }
        reservationsByDate[reservation.date].push(reservation);
      });

      // Ordenar as datas
      const sortedDates = Object.keys(reservationsByDate).sort((a, b) => {
        return new Date(a).getTime() - new Date(b).getTime();
      });

      const handleCancelReservation = async (id) => {
        if (confirm("Tem certeza que deseja cancelar esta reserva?")) {
          try {
            await fetch(`${API_URL}reservations&id=${id}`, {
              method: "DELETE",
            });
            // Em um sistema real, atualizaríamos a lista de reservas
            alert("Reserva cancelada com sucesso!");
            window.location.reload(); // Solução simples para atualizar a página
          } catch (error) {
            console.error("Erro ao cancelar reserva:", error);
          }
        }
      };

      if (sortedReservations.length === 0) {
        return (
          <div className="text-center py-8">
            <p className="text-lg">Você não tem reservas.</p>
            <p className="text-gray-500">Vá para a aba "Fazer Reserva" para reservar uma sala.</p>
          </div>
        );
      }

      return (
        <div className="space-y-6">
          {sortedDates.map((date) => (
            <div key={date} className="space-y-4">
              <h3 className="text-lg font-medium">{formatDate(date)}</h3>

              <div className="space-y-3">
                {reservationsByDate[date].map((reservation) => (
                  <Card key={reservation.id}>
                    <CardHeader className="pb-2">
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className="text-lg font-medium">{reservation.title}</h4>
                          <p className="text-sm text-gray-500">{getRoomName(reservation.roomId)}</p>
                        </div>
                        <Button variant="destructive" onClick={() => handleCancelReservation(reservation.id)}>
                          Cancelar
                        </Button>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div className="space-y-2">
                        <p className="text-sm">
                          <span className="font-medium">Horário:</span> {reservation.startTime} - {reservation.endTime}
                        </p>
                        {reservation.description && (
                          <p className="text-sm">
                            <span className="font-medium">Descrição:</span> {reservation.description}
                          </p>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          ))}
        </div>
      );
    };

    // Componente principal da aplicação
    const App = () => {
      const [activeTab, setActiveTab] = React.useState("rooms");
      const [rooms, setRooms] = React.useState([]);
      const [reservations, setReservations] = React.useState([]);
      const [selectedRoom, setSelectedRoom] = React.useState(null);
      const [loading, setLoading] = React.useState(true);

      // Carregar dados da API
      React.useEffect(() => {
        const fetchData = async () => {
          try {
            setLoading(true);

            // Para facilitar o desenvolvimento, vamos usar dados simulados
            // Em produção, substitua por chamadas reais à API
            const useMockData = true; // Altere para false para usar a API real

            let roomsData, reservationsData;

            if (useMockData) {
              // Dados simulados
              roomsData = [
                {
                  id: 1,
                  name: "Sala de Conferência A",
                  location: "Andar 1",
                  capacity: 20,
                  features: ["Projetor", "Videoconferência", "Quadro branco"],
                },
                {
                  id: 2,
                  name: "Sala de Reuniões B",
                  location: "Andar 2",
                  capacity: 8,
                  features: ["TV", "Quadro branco"],
                },
                {
                  id: 3,
                  name: "Auditório",
                  location: "Térreo",
                  capacity: 50,
                  features: ["Projetor", "Sistema de som", "Microfones"],
                },
                {
                  id: 4,
                  name: "Sala de Treinamento",
                  location: "Andar 3",
                  capacity: 15,
                  features: ["Computadores", "Projetor", "Quadro branco"],
                },
                {
                  id: 5,
                  name: "Sala de Criatividade",
                  location: "Andar 2",
                  capacity: 10,
                  features: ["Lousa digital", "Puffs", "Materiais de desenho"],
                },
              ];

              reservationsData = [
                {
                  id: 1,
                  roomId: 1,
                  userId: 1,
                  title: "Reunião de Planejamento",
                  description: "Discussão sobre o próximo trimestre",
                  date: "2025-05-22",
                  startTime: "09:00",
                  endTime: "10:30",
                },
                {
                  id: 2,
                  roomId: 3,
                  userId: 1,
                  title: "Apresentação de Projeto",
                  description: "Apresentação final para os stakeholders",
                  date: "2025-05-23",
                  startTime: "14:00",
                  endTime: "16:00",
                },
                {
                  id: 3,
                  roomId: 2,
                  userId: 1,
                  title: "Entrevista de Candidato",
                  date: "2025-05-21",
                  startTime: "11:00",
                  endTime: "12:00",
                },
              ];
            } else {
              // Chamadas reais à API
              const roomsResponse = await fetch(`${API_URL}rooms`);
              const reservationsResponse = await fetch(`${API_URL}reservations`);

              roomsData = await roomsResponse.json();
              reservationsData = await reservationsResponse.json();
            }

            setRooms(roomsData);
            setReservations(reservationsData);
          } catch (error) {
            console.error("Erro ao buscar dados:", error);
          } finally {
            setLoading(false);
          }
        };

        fetchData();
      }, []);

      const handleRoomSelect = (room) => {
        setSelectedRoom(room);
        setActiveTab("reserve");
      };

      const handleCreateReservation = async (reservation) => {
        try {
          // Em um cenário real, esta seria uma chamada POST para sua API PHP
          // Simulando uma chamada de API bem-sucedida
          const newId = Math.max(0, ...reservations.map((r) => r.id)) + 1;
          const newReservation = { id: newId, ...reservation };

          setReservations([...reservations, newReservation]);
          setActiveTab("reservations");
          alert("Reserva criada com sucesso!");
        } catch (error) {
          console.error("Erro ao criar reserva:", error);
          alert("Erro ao criar reserva. Tente novamente.");
        }
      };

      if (loading) {
        return (
          <div className="flex justify-center items-center h-screen">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mx-auto"></div>
              <p className="mt-4 text-gray-700">Carregando...</p>
            </div>
          </div>
        );
      }

      return (
        <div className="min-h-screen bg-gray-100 py-8">
          <div className="container max-w-5xl mx-auto px-4">
            <header className="mb-8 text-center">
              <h1 className="text-3xl font-bold text-gray-900 mb-2">Sistema de Reserva de Salas</h1>
              <p className="text-gray-600">Gerencie reservas de salas de forma simples e eficiente</p>
            </header>

            <main>
              <Tabs value={activeTab} onChange={setActiveTab}>
                <TabsList>
                  <TabsTrigger value="rooms">Salas Disponíveis</TabsTrigger>
                  <TabsTrigger value="reserve">Fazer Reserva</TabsTrigger>
                  <TabsTrigger value="reservations">Minhas Reservas</TabsTrigger>
                </TabsList>

                <TabsContent value="rooms">
                  <RoomList rooms={rooms} onSelectRoom={handleRoomSelect} />
                </TabsContent>

                <TabsContent value="reserve">
                  <ReservationForm
                    rooms={rooms}
                    selectedRoom={selectedRoom}
                    onCreateReservation={handleCreateReservation}
                  />
                </TabsContent>

                <TabsContent value="reservations">
                  <ReservationList reservations={reservations} rooms={rooms} />
                </TabsContent>
              </Tabs>
            </main>

            <footer className="mt-12 pt-6 border-t border-gray-200 text-center text-gray-500 text-sm">
              <p>&copy; 2025 Sistema de Reserva de Salas. Todos os direitos reservados.</p>
            </footer>
          </div>
        </div>
      );
    };

    // Renderizar a aplicação
    const root = ReactDOM.createRoot(document.getElementById("root"));
    root.render(<App />);
  </script>

  <!-- Script para criar o banco de dados -->
  <script>
    // Este script é apenas para referência e não é executado automaticamente
    // Para criar o banco de dados, copie o conteúdo abaixo e execute no seu MySQL
    /*
    -- Criação do banco de dados
    CREATE DATABASE IF NOT EXISTS room_reservation;
    USE room_reservation;

    -- Tabela de salas
    CREATE TABLE rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(100) NOT NULL,
        capacity INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Tabela de características das salas
    CREATE TABLE room_features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        feature_name VARCHAR(100) NOT NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    );

    -- Tabela de usuários
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Tabela de reservas
    CREATE TABLE reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    -- Inserir dados de exemplo
    INSERT INTO rooms (name, location, capacity) VALUES
    ('Sala de Conferência A', 'Andar 1', 20),
    ('Sala de Reuniões B', 'Andar 2', 8),
    ('Auditório', 'Térreo', 50),
    ('Sala de Treinamento', 'Andar 3', 15),
    ('Sala de Criatividade', 'Andar 2', 10);

    -- Inserir características das salas
    INSERT INTO room_features (room_id, feature_name) VALUES
    (1, 'Projetor'), (1, 'Videoconferência'), (1, 'Quadro branco'),
    (2, 'TV'), (2, 'Quadro branco'),
    (3, 'Projetor'), (3, 'Sistema de som'), (3, 'Microfones'),
    (4, 'Computadores'), (4, 'Projetor'), (4, 'Quadro branco'),
    (5, 'Lousa digital'), (5, 'Puffs'), (5, 'Materiais de desenho');

    -- Inserir usuário de exemplo
    INSERT INTO users (name, email, password) VALUES
    ('Usuário Teste', 'usuario@teste.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- senha: password
    */
  </script>
</body>
</html>