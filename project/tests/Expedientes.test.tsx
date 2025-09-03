import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { vi } from 'vitest';
import Expedientes from '../src/pages/Expedientes/Expedientes';
import { expedientesAPI } from '../src/api';

// Mock the API
vi.mock('../src/api', () => ({
  expedientesAPI: {
    getAll: vi.fn(),
    create: vi.fn(),
  },
}));

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const mockExpedientes = [
  {
    id: '1',
    numero: '2024-001',
    materia: 'Civil',
    juzgado: 'Juzgado 1',
    especialista: 'Juan Pérez',
    demandante: 'María García',
    demandado: 'Pedro López',
    archivo: { id: '1', nombre: 'documento.docx' }
  },
  {
    id: '2',
    numero: '2024-002',
    materia: 'Penal',
    juzgado: 'Juzgado 2',
    especialista: 'Ana Martín',
    demandante: 'Carlos Ruiz',
    demandado: 'Laura Sánchez',
    archivo: null
  }
];

const renderWithRouter = (component: React.ReactElement) => {
  return render(
    <BrowserRouter>
      {component}
    </BrowserRouter>
  );
};

describe('Expedientes', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (expedientesAPI.getAll as any).mockResolvedValue(mockExpedientes);
  });

  test('renders expedientes table with data', async () => {
    renderWithRouter(<Expedientes />);

    expect(screen.getByText('Expedientes')).toBeInTheDocument();
    expect(screen.getByText('Nuevo Expediente')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText('2024-001')).toBeInTheDocument();
      expect(screen.getByText('2024-002')).toBeInTheDocument();
      expect(screen.getByText('Civil')).toBeInTheDocument();
      expect(screen.getByText('Penal')).toBeInTheDocument();
    });
  });

  test('shows search functionality', async () => {
    const user = userEvent.setup();
    renderWithRouter(<Expedientes />);

    const searchInput = screen.getByPlaceholderText('Buscar por número de expediente...');
    expect(searchInput).toBeInTheDocument();

    await user.type(searchInput, '2024-001');
    await user.click(screen.getByText('Buscar'));

    expect(expedientesAPI.getAll).toHaveBeenCalledWith(1, '2024-001');
  });

  test('navigates to expediente detail on row click', async () => {
    const user = userEvent.setup();
    renderWithRouter(<Expedientes />);

    await waitFor(() => {
      const firstRow = screen.getByText('2024-001').closest('tr');
      expect(firstRow).toBeInTheDocument();
    });

    const firstRow = screen.getByText('2024-001').closest('tr');
    await user.click(firstRow!);

    expect(mockNavigate).toHaveBeenCalledWith('/expedientes/1');
  });

  test('shows create modal when clicking new expediente button', async () => {
    const user = userEvent.setup();
    renderWithRouter(<Expedientes />);

    const newButton = screen.getByText('Nuevo Expediente');
    await user.click(newButton);

    await waitFor(() => {
      expect(screen.getByText('Crear Nuevo Expediente')).toBeInTheDocument();
    });
  });

  test('displays file status correctly', async () => {
    renderWithRouter(<Expedientes />);

    await waitFor(() => {
      const yesStatus = screen.getByText('Sí');
      const noStatus = screen.getByText('No');
      
      expect(yesStatus).toBeInTheDocument();
      expect(noStatus).toBeInTheDocument();
    });
  });
});