
            // 2. Buscar carpetas que parecen años y moverlas como hijas
            $year_folders = $wpdb->get_results(
                "SELECT * FROM $table_folders WHERE name REGEXP '^20[0-9]{2}$' AND (parent_id IS NULL OR parent_id = 0)"
            );
            
            $moved_count = 0;
            foreach ($year_folders as $year_folder) {
                $wpdb->update(
                    $table_folders,
                    array('parent_id' => $junta_directiva_id),
                    array('id' => $year_folder->id),
                    array('%d'),
                    array('%d')
                );
                
                $steps[] = "Movida carpeta '{$year_folder->name}' como hija de 'Actas de Junta Directiva'";
                $moved_count++;
            }
            
            // 3. Reorganizar actas existentes
            $this->organize_existing_actas();
            $steps[] = "Reorganizadas actas existentes en la nueva estructura";
            
            wp_send_json_success(array(
                'message' => "Migración completada: $moved_count carpetas movidas",
                'steps' => $steps
            ));
            
        } catch (Exception $e) {
            error_log('Error en migración: ' . $e->getMessage());
            wp_send_json_error('Error en migración: ' . $e->getMessage());
        }
    }
