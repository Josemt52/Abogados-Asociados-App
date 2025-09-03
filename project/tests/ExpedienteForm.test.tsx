import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import ExpedienteForm from '../src/components/ExpedienteForm/ExpedienteForm';
import { expedientesAPI } from '../src/api';

// Mock the API
vi.mock('../src/api', () => ({
  expedientesAPI: {
    create: vi.fn(),
    update: vi.fn(),
  },
}));

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

const mockOnSuccess = vi.fn();
const mockOnCancel = vi.fn();

describe('ExpedienteForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('renders form fields correctly for new expediente', () => {
    render(
      <ExpedienteForm 
        onSuccess={mockOnSuccess}
        onCancel={mockOnCancel}
      />
    );

    expect(screen.getByLabelText(/número/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/materia/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/juzgado/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/especialista/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/tercero/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/demandado/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/demandante/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/estado/i)).toBeInTheDocument();

    expect(screen.getByText('Crear Expediente')).toBeInTheDocument();
  });

  test('renders form with existing data for editing', () => {
    const existingExpediente = {
      id: '1',
      numero: '2024-001',
      materia: 'Civil',
      juzgado: 'Juzgado 1',
      especialista: 'Juan Pérez'
    };

    render(
      <ExpedienteForm 
        expediente={existingExpediente}
        onSuccess={mockOnSuccess}
        onCancel={mockOnCancel}
      />
    );

    expect(screen.getByDisplayValue('2024-001')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Civil')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Juzgado 1')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Juan Pérez')).toBeInTheDocument();
    
    expect(screen.getByText('Actualizar Expediente')).toBeInTheDocument();
  });

  test('validates required fields', async () => {
    const user = userEvent.setup();
    render(
      <ExpedienteForm 
        onSuccess={mockOnSuccess}
        onCancel={mockOnCancel}
      />
    );

    const submitButton = screen.getByText('Crear Expediente');
    await user.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('El número es obligatorio')).toBeInTheDocument();
      expect(screen.getByText('La materia es obligatoria')).toBeInTheDocument();
      expect(screen.getByText('El juzgado es obligatorio')).toBeInTheDocument();
      expect(screen.getByText('El especialista es obligatorio')).toBeInTheDocument();
    });

    expect(expedientesAPI.create).not.toHaveBeenCalled();
  });

  test('submits form with valid data', async () => {
    const user = userEvent.setup();
    (expedientesAPI.create as any).mockResolvedValue({});

    render(
      <ExpedienteForm 
        onSuccess={mockOnSuccess}
        onCancel={mockOnCancel}
      />
    );

    await user.type(screen.getByLabelText(/número/i), '2024-003');
    await user.type(screen.getByLabelText(/materia/i), 'Laboral');
    await user.type(screen.getByLabelText(/juzgado/i), 'Juzgado 3');
    await user.type(screen.getByLabelText(/especialista/i), 'Carlos López');

    const submitButton = screen.getByText('Crear Expediente');
    await user.click(submitButton);

    await waitFor(() => {
      expect(expedientesAPI.create).toHaveBeenCalledWith({
        numero: '2024-003',
        materia: 'Laboral',
        juzgado: 'Juzgado 3',
        especialista: 'Carlos López',
        tercero: '',
        demandado: '',
        demandante: '',
        estado: '',
        nombreArchivo: '',
      });
    });

    expect(mockOnSuccess).toHaveBeenCalled();
  });

  test('calls cancel handler when cancel button is clicked', async () => {
    const user = userEvent.setup();
    render(
      <ExpedienteForm 
        onSuccess={mockOnSuccess}
        onCancel={mockOnCancel}
      />
    );

    const cancelButton = screen.getByText('Cancelar');
    await user.click(cancelButton);

    expect(mockOnCancel).toHaveBeenCalled();
  });
});