package j.m.services;

import java.io.File;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.nio.file.StandardCopyOption;
import java.time.Duration;
import java.time.Instant;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Properties;
import java.util.stream.Collectors;
import java.util.stream.Stream;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

public class BackupService {

    private static final String BACKUP_FOLDER = "backups";
    private static final String DB_FILE_NAME = "expedientes.db";
    private static final String CONFIG_FILE = "backup_config.properties";
    private static final String LAST_BACKUP_KEY = "lastBackupTimestamp";
    private static final String WORD_FOLDER_PATH = System.getProperty("user.home") + "/Desktop/ExpedientesWord";

    // --- NUEVA CONSTANTE PARA CONTROLAR EL NÚMERO DE RESPALDOS ---
    private static final int MAX_BACKUPS = 3;

    public void checkAndPerformBackup() {
        try {
            Instant lastBackup = getLastBackupTimestamp();

            if (lastBackup == null || Duration.between(lastBackup, Instant.now()).toHours() >= 24) {
                System.out.println("Iniciando respaldo completo...");
                
                Path backupDir = Paths.get(BACKUP_FOLDER);
                if (!Files.exists(backupDir)) Files.createDirectories(backupDir);

                // 1. Respaldar la base de datos y limpiar los antiguos
                createDbBackup(backupDir);
                cleanupOldBackups(backupDir, ".db");

                // 2. Respaldar los documentos y limpiar los antiguos
                createDocumentsBackup(backupDir);
                cleanupOldBackups(backupDir, ".zip");

                saveCurrentBackupTimestamp();
                System.out.println("Respaldo y limpieza completados con éxito.");

            } else {
                long hoursSinceLastBackup = Duration.between(lastBackup, Instant.now()).toHours();
                System.out.println("No se necesita un nuevo respaldo. Han pasado " + hoursSinceLastBackup + " horas.");
            }
        } catch (IOException e) {
            System.err.println("Error durante el proceso de respaldo: " + e.getMessage());
            e.printStackTrace();
        }
    }

    // --- NUEVO MÉTODO PARA LIMPIAR RESPALDOS ANTIGUOS ---
    private void cleanupOldBackups(Path backupDir, String fileExtension) throws IOException {
        System.out.println("Buscando respaldos antiguos con extensión '" + fileExtension + "' para limpiar...");

        List<Path> backups;
        try (Stream<Path> stream = Files.list(backupDir)) {
            backups = stream
                .filter(p -> p.toString().endsWith(fileExtension))
                .sorted() // Los nombres de archivo con timestamp se ordenan alfabéticamente de más viejo a más nuevo
                .collect(Collectors.toList());
        }

        if (backups.size() > MAX_BACKUPS) {
            int filesToDelete = backups.size() - MAX_BACKUPS;
            System.out.println("Se encontraron " + backups.size() + " respaldos. Se eliminarán los " + filesToDelete + " más antiguos.");
            for (int i = 0; i < filesToDelete; i++) {
                Path oldFile = backups.get(i);
                System.out.println("Eliminando respaldo antiguo: " + oldFile.getFileName());
                Files.delete(oldFile);
            }
        } else {
            System.out.println("No se necesita limpieza. Total de respaldos ("+ fileExtension +"): " + backups.size());
        }
    }
    
    private void createDbBackup(Path backupDir) throws IOException {
        // (El resto de los métodos se mantienen igual que en la versión anterior)
        Path sourcePath = Paths.get(DB_FILE_NAME);
        if (!Files.exists(sourcePath)) {
            System.err.println("Archivo de base de datos no encontrado: " + DB_FILE_NAME);
            return;
        }
        
        String timestamp = getTimestamp();
        Path destinationPath = backupDir.resolve(DB_FILE_NAME.replace(".db", "_" + timestamp + ".db"));
        Files.copy(sourcePath, destinationPath, StandardCopyOption.REPLACE_EXISTING);
        System.out.println("Base de datos respaldada en: " + destinationPath);
    }
    
    private void createDocumentsBackup(Path backupDir) throws IOException {
        Path sourceDir = Paths.get(WORD_FOLDER_PATH);
        if (!Files.exists(sourceDir) || !Files.isDirectory(sourceDir)) {
            System.err.println("Carpeta de documentos Word no encontrada: " + WORD_FOLDER_PATH);
            return;
        }

        String timestamp = getTimestamp();
        String zipFileName = "WordDocuments_" + timestamp + ".zip";
        Path zipFilePath = backupDir.resolve(zipFileName);

        try (ZipOutputStream zos = new ZipOutputStream(new FileOutputStream(zipFilePath.toFile()))) {
            try (Stream<Path> paths = Files.walk(sourceDir)) {
                paths
                    .filter(path -> !Files.isDirectory(path))
                    .forEach(path -> {
                        ZipEntry zipEntry = new ZipEntry(sourceDir.relativize(path).toString());
                        try {
                            zos.putNextEntry(zipEntry);
                            Files.copy(path, zos);
                            zos.closeEntry();
                        } catch (IOException e) {
                            System.err.println("Error al comprimir archivo: " + path + " - " + e.getMessage());
                        }
                    });
            }
        }
        System.out.println("Documentos Word respaldados en: " + zipFilePath);
    }
    
    private String getTimestamp() {
        return DateTimeFormatter.ofPattern("yyyy-MM-dd_HH-mm-ss")
                                .withZone(java.time.ZoneId.systemDefault())
                                .format(Instant.now());
    }

    private Instant getLastBackupTimestamp() throws IOException {
        Properties props = new Properties();
        File configFile = new File(CONFIG_FILE);

        if (!configFile.exists()) {
            return null;
        }
        
        try (FileReader reader = new FileReader(configFile)) {
            props.load(reader);
            String timestampStr = props.getProperty(LAST_BACKUP_KEY);
            return timestampStr != null ? Instant.parse(timestampStr) : null;
        }
    }

    private void saveCurrentBackupTimestamp() throws IOException {
        Properties props = new Properties();
        props.setProperty(LAST_BACKUP_KEY, Instant.now().toString());

        try (FileWriter writer = new FileWriter(CONFIG_FILE)) {
            props.store(writer, "Backup Configuration");
        }
    }
}