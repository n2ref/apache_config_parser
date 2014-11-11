<?php



/**
 * Class Apache_Config_Parser
 */
class Apache_Config_Parser {

    /**
     * @var string
     */
    private $apache_config = '';


    /**
     * @param string $apache_config
     * @throws Exception
     */
    public function __construct($apache_config) {

        if ( ! file_exists($apache_config)) {
            throw new Exception("Не найден конфигурационный файл сервера Apache ({$apache_config})");
        }
        if ( ! is_readable($apache_config)) {
            throw new Exception("Нет прав на чтение конфигурационного файла сервера Apache ({$apache_config})");
        }

        $this->apache_config = $apache_config;
    }


    /**
     * @return array
     * @throws Exception
     */
    public function getApacheHosts() {

        $config = $this->getFullConfig($this->apache_config);

        $hosts = array();
        $matches = array();
        preg_match_all('~(?:^\s*|\n\s*)<VirtualHost[^>]*>(.*?)</VirtualHost>~is', $config, $matches);

        if ( ! empty($matches[1])) {
            foreach ($matches[1] as $v_host) {
                $server_name_matche = array();
                preg_match('~(?:^\s*|\n\s*)ServerName\s+(?:"|)([^"\s:]*)(?:"|)~is', $v_host, $server_name_matche);

                if ( ! empty($server_name_matche[1])) {
                    $doc_root_matche = array();
                    preg_match('~(?:^\s*|\n\s*)DocumentRoot\s+(?:"|)([^"\s]*)(?:"|)~is', $v_host, $doc_root_matche);

                    $server_aliases_matche = array();
                    preg_match_all('~(^\s*|\n\s*|^\s*#\s*|\n\s*#\s*)ServerAlias\s+(?:"|)([^"\n]*)(?:"|)~is', $v_host, $server_aliases_matche);

                    $aliases = array();
                    if ( ! empty($server_aliases_matche[2])) {
                        foreach ($server_aliases_matche[2] as $key=>$alias) {
                            if (preg_match('~\s+~s', $alias)) {
                                $splited_aliases = preg_split('~\s+~s', $alias);
                                foreach ($splited_aliases as $splited_alias) {
                                    if ($splited_alias != '') {
                                        $aliases[] = array(
                                            'is_active' => trim($server_aliases_matche[1][$key]) != '#' ? true : false,
                                            'name'      => $splited_alias
                                        );
                                    }
                                }
                            } else {
                                $aliases[] = array(
                                    'is_active' => trim($server_aliases_matche[1][$key]) != '#' ? true : false,
                                    'name'      => $alias
                                );
                            }
                        }
                    }

                    $hosts[$server_name_matche[1]] = array(
                        'is_active'     => true,
                        'document_root' => $doc_root_matche[1],
                        'aliases'       => $aliases
                    );
                }
            }
        }

        return $hosts;
    }


    /**
     * Ищет в конфиге параметры include и includeOption
     * и рекурсивно заменяет их на содержимое подключаемых файлов
     * @return string
     */
    private function getFullConfig($config_path) {

        $config_dir   = dirname($config_path);
        $config_text  = file_get_contents($config_path);

        return preg_replace_callback(
            '~(^\s*Include\s+("|)([^"\n]*)("|)(\n|$)|^\s*IncludeOptional\s+("|)([^"\n]*)("|)(\n|$))~Um',
            function ($matches) use ($config_dir) {

                $include = $matches[3];

                if ( ! empty($include)) {
                    //  Папка с путем от корня
                    if ($include{0} == '/' && $include{strlen($include) - 1} == '/') {

                        $configs = $this->getConfigs($include);
                        $content = '';
                        if ( ! empty($configs)) {
                            foreach ($configs as $config_path) {
                                $content .= $this->getFullConfig($config_path);
                            }
                        }
                        return $content;



                    // Файл с путем от корня
                    } elseif ($include{0} == '/') {

                        $star_pos = strpos($include, '*');
                        if ($star_pos !== false) {
                            $sub_dir = substr($include, 0, $star_pos);
                            $sub_dir = strrpos($sub_dir, '/');
                            $sub_dir = substr($include, 0, $sub_dir);
                            $sub_dir = realpath($sub_dir);

                            $configs = $this->getConfigs($sub_dir);
                            $content = '';
                            if ( ! empty($configs)) {
                                foreach ($configs as $config_path) {
                                    $regex_include = str_replace('~', '\~', $include);
                                    $regex_include = str_replace('.', '\.', $regex_include);
                                    $regex_include = str_replace('*', '.*', $regex_include);
                                    if (preg_match("~{$regex_include}$~U", $config_path)) {
                                        $content .= $this->getFullConfig($config_path);
                                    }
                                }
                            }

                            return $content;
                        } else {
                            return $this->getFullConfig($include);
                        }



                    // Папка с путем от текущей директории
                    } elseif ($include{strlen($include) - 1} == '/') {
                        $configs = $this->getConfigs($config_dir . '/' . substr($include, 0, -1));
                        $content = '';
                        if ( ! empty($configs)) {
                            foreach ($configs as $config_path) {
                                $content .= $this->getFullConfig($config_path);
                            }
                        }
                        return $content;



                    // Файл с путем от текущей директории
                    } else {
                        // Поиск конфигов по регулярке
                        $star_pos = strpos($include, '*');
                        if ($star_pos !== false) {
                            $sub_dir = substr($include, 0, $star_pos);
                            $sub_dir = strrpos($sub_dir, '/');
                            $sub_dir = substr($include, 0, $sub_dir);
                            $sub_dir = realpath($sub_dir);

                            $configs = $this->getConfigs($config_dir . '/' . $sub_dir);
                            $content = '';
                            if ( ! empty($configs)) {
                                foreach ($configs as $config_path) {
                                    $regex_include = str_replace('~', '\~', $include);
                                    $regex_include = str_replace('.', '\.', $regex_include);
                                    $regex_include = str_replace('*', '.*', $regex_include);
                                    if (preg_match("~{$regex_include}$~U", $config_path)) {
                                        $content .= $this->getFullConfig($config_path);
                                    }
                                }
                            }

                            return $content;

                        // Простое подключение конфига
                        } else {
                            return $this->getFullConfig($config_dir . '/' . $include);
                        }
                    }
                } else {
                    return '';
                }
            },
            $config_text);
    }


    /**
     * Получение конфигурационных файлов в директории
     * и во всех вложеных директориях
     * @param string $dir_path
     * @param bool $recursive
     * @return array
     */
    private function getConfigs($dir_path, $recursive = true) {

        if ( ! is_dir($dir_path)) {
            return array();
        }

        $config_files = array();

        if ($handle = opendir($dir_path)) {
            while ($element_name = readdir($handle)) {
                if ($element_name != "." && $element_name != "..") {
                    if (is_file($dir_path . '/' . $element_name)) {
                        $config_files[] = $dir_path . '/' . $element_name;

                    } elseif ($recursive && is_dir($dir_path . '/' . $element_name)) {
                        $configs = $this->getConfigs($dir_path . '/' . $element_name);
                        if ( ! empty($configs)) {
                            foreach ($configs as $config) {
                                $config_files[] = $config;
                            }
                        }
                    }
                }
            }
        }

        return $config_files;
    }
} 