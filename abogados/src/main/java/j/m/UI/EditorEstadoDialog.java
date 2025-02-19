package j.m.UI;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Font;
import java.awt.event.ActionEvent;
import java.io.StringWriter;

import javax.swing.JButton;
import javax.swing.JColorChooser;
import javax.swing.JComboBox;
import javax.swing.JPanel;
import javax.swing.JScrollPane;
import javax.swing.JTextPane;
import javax.swing.JToolBar;
import javax.swing.text.BadLocationException;
import javax.swing.text.Element;
import javax.swing.text.SimpleAttributeSet;
import javax.swing.text.StyleConstants;
import javax.swing.text.StyledDocument;
import javax.swing.text.StyledEditorKit;
import javax.swing.text.html.HTMLDocument;
import javax.swing.text.html.HTMLEditorKit;

/**
 * Panel que usa StyledEditorKit para formato en vivo y luego convierte a HTML.
 */
public class EditorEstadoDialog extends JPanel {
    private JTextPane textPane;
    private StyledDocument doc;

    public EditorEstadoDialog(String contenidoHTML) {
        setLayout(new BorderLayout());

        // Usamos un JTextPane con StyledEditorKit
        textPane = new JTextPane();
        textPane.setEditorKit(new StyledEditorKit());
        doc = (StyledDocument) textPane.getDocument();

        // Opcional: Cargar contenidoHTML (como texto simple).
        if (contenidoHTML != null && !contenidoHTML.isEmpty()) {
            setHTML(contenidoHTML);
        }

        JScrollPane scrollPane = new JScrollPane(textPane);

        // Barra de herramientas
        JToolBar toolBar = new JToolBar();
        toolBar.setFloatable(false);

        // Botón Negrita
        JButton btnNegrita = new JButton("B");
        btnNegrita.setFont(new Font("Arial", Font.BOLD, 12));
        btnNegrita.addActionListener(e -> new StyledEditorKit.BoldAction()
            .actionPerformed(new ActionEvent(textPane, ActionEvent.ACTION_PERFORMED, null)));
        toolBar.add(btnNegrita);

        // Botón Cursiva
        JButton btnCursiva = new JButton("I");
        btnCursiva.setFont(new Font("Arial", Font.ITALIC, 12));
        btnCursiva.addActionListener(e -> new StyledEditorKit.ItalicAction()
            .actionPerformed(new ActionEvent(textPane, ActionEvent.ACTION_PERFORMED, null)));
        toolBar.add(btnCursiva);

        // Botón Subrayado
        JButton btnSubrayado = new JButton("U");
        btnSubrayado.setFont(new Font("Arial", Font.PLAIN, 12));
        btnSubrayado.addActionListener(e -> new StyledEditorKit.UnderlineAction()
            .actionPerformed(new ActionEvent(textPane, ActionEvent.ACTION_PERFORMED, null)));
        toolBar.add(btnSubrayado);

        // Separador
        toolBar.addSeparator();

        // Combo para Tamaño de Fuente
        JComboBox<String> comboTamano = new JComboBox<>(new String[]{"12","14","16","18","20","24","28"});
        comboTamano.addActionListener(e -> {
            String selectedSize = (String) comboTamano.getSelectedItem();
            cambiarTamanoFuente(Integer.parseInt(selectedSize));
        });
        toolBar.add(comboTamano);

        // Botón Color
        JButton btnColor = new JButton("Color");
        btnColor.addActionListener(e -> {
            Color color = JColorChooser.showDialog(this, "Seleccionar Color", Color.BLACK);
            if (color != null) {
                SimpleAttributeSet attr = new SimpleAttributeSet();
                StyleConstants.setForeground(attr, color);
                textPane.setCharacterAttributes(attr, false);
            }
        });
        toolBar.add(btnColor);

        // Separador
        toolBar.addSeparator();

        // Botones de Alineación
        JButton btnIzquierda = new JButton("Left");
        btnIzquierda.addActionListener(e -> alinear(StyleConstants.ALIGN_LEFT));
        toolBar.add(btnIzquierda);

        JButton btnCentro = new JButton("Center");
        btnCentro.addActionListener(e -> alinear(StyleConstants.ALIGN_CENTER));
        toolBar.add(btnCentro);

        JButton btnDerecha = new JButton("Right");
        btnDerecha.addActionListener(e -> alinear(StyleConstants.ALIGN_RIGHT));
        toolBar.add(btnDerecha);

        add(toolBar, BorderLayout.NORTH);
        add(scrollPane, BorderLayout.CENTER);
    }

    /**
     * Cambia el tamaño de fuente de la selección.
     */
    private void cambiarTamanoFuente(int size) {
        int start = textPane.getSelectionStart();
        int end = textPane.getSelectionEnd();
        if (start == end) return; // Nada seleccionado

        SimpleAttributeSet attr = new SimpleAttributeSet();
        StyleConstants.setFontSize(attr, size);
        doc.setCharacterAttributes(start, end - start, attr, false);
    }

    /**
     * Alinea el párrafo (izquierda, centro, derecha).
     */
    private void alinear(int alignment) {
        int start = textPane.getSelectionStart();
        int end = textPane.getSelectionEnd();
        if (start == end) return;

        Element element = doc.getParagraphElement(start);
        int length = end;
        while (element.getEndOffset() < end) {
            SimpleAttributeSet attr = new SimpleAttributeSet(element.getAttributes());
            StyleConstants.setAlignment(attr, alignment);
            doc.setParagraphAttributes(element.getStartOffset(),
                element.getEndOffset() - element.getStartOffset(), attr, false);

            element = doc.getParagraphElement(element.getEndOffset());
        }
        // Para la última parte
        SimpleAttributeSet attr = new SimpleAttributeSet(element.getAttributes());
        StyleConstants.setAlignment(attr, alignment);
        doc.setParagraphAttributes(element.getStartOffset(),
            length - element.getStartOffset(), attr, false);
    }

    /**
     * Retorna el contenido en HTML a partir del StyledDocument.
     */
    public String getHTML() {
        // Convertimos StyledDocument a HTML
        try {
            HTMLEditorKit kit = new HTMLEditorKit();
            HTMLDocument htmlDoc = (HTMLDocument) kit.createDefaultDocument();

            StringWriter writer = new StringWriter();
            kit.write(writer, doc, 0, doc.getLength());
            return writer.toString();
        } catch (Exception e) {
            e.printStackTrace();
        }
        return "";
    }

    /**
     * Carga un contenido HTML (simple) y lo pasa a StyledDocument.
     */
    public void setHTML(String html) {
        // Aquí podríamos parsear HTML y aplicarlo al doc,
        // pero por simplicidad, solo insertamos como texto.
        try {
            doc.remove(0, doc.getLength());
            doc.insertString(0, filtrarEtiquetasHTML(html), null);
        } catch (BadLocationException e) {
            e.printStackTrace();
        }
    }

    /**
     * Si deseas filtrar etiquetas HTML avanzadas, puedes hacerlo aquí.
     */
    private String filtrarEtiquetasHTML(String html) {
        // Podrías usar Jsoup.parse(html).text() para quedarte con texto plano
        // o algo más sofisticado. Aquí solo devolvemos el contenido tal cual.
        return html.replaceAll("\\<[^>]*>",""); 
    }
}
