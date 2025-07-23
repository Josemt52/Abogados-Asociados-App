package j.m.ui;

import j.m.services.BackupService;


public class MainApp {
    public static void main(String[] args) {
        // Ejecutar backup automático al iniciar la app
        new BackupService().checkAndPerformBackup();
        // Cargar datos iniciales (usuarios y roles)
        new j.m.services.DataLoader().loadInitialData();
        javax.swing.SwingUtilities.invokeLater(() -> {
            new LoginFrame();
        });
    }
}