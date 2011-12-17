<?php
/**
* PvPGN plain to SQL2 format converter
* http://naki.info/
* 
* Copyright 2011 Naki (naki@pvpgn.pl). All rights reserved.
* 
* License: FreeBSD
* 
* Redistribution and use in source and binary forms, with or without modification, are
* permitted provided that the following conditions are met:
* 
*    1. Redistributions of source code must retain the above copyright notice, this list of
*       conditions and the following disclaimer.
* 
*    2. Redistributions in binary form must reproduce the above copyright notice, this list
*       of conditions and the following disclaimer in the documentation and/or other materials
*       provided with the distribution.
* 
* THIS SOFTWARE IS PROVIDED BY NAKI "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
* INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
* FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL NAKI OR
* CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
* SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
* ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
* NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
* ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
* 
* The views and conclusions contained in the software and documentation are those of the
* authors and should not be interpreted as representing official policies, either expressed
* or implied, of Naki.
*/

class pvpgn_plain_to_sql2 {
    /**
    * Convert users accounts.
    * 
    * @var mixed
    */
    protected $convert_users = true;

    /**
    * Convert created clans.
    * 
    * @var mixed
    */
    protected $convert_clans = true;

    /**
    * Convert arranged teams.
    * 
    * @var mixed
    */
    protected $convert_teams = true;

    /**
    * Sort output data by identifiers.
    * 
    * @var mixed
    */
    protected $sort_output_data = true;

    /**
    * Set time limit, generate data may take some time, in minutes, -1 to disable. 
    * 
    * @var mixed
    */
    protected $time_limit = 15;

    /**
    * Set memory limit, many account may take big amount, in megabytes, -1 to disable.
    * 
    * @var mixed
    */
    protected $memory_limit = 512;

    /**
    * Exit script (die) on first occurred error.
    * 
    * @var mixed
    */
    protected $exit_on_first_error = false;

    /**
    * PvPGN absolute path, with trailing slash.
    * 
    * @var mixed
    */
    protected $pvpgn_absolute_path = "/usr/local/";

    /**
    * Users relative directory, with trailing slash.
    * 
    * @var mixed
    */
    protected $users_relative_path = "var/users/";

    /**
    * Clans relative directory, with trailing slash.
    * 
    * @var mixed
    */
    protected $clans_relative_path = "var/clans/";

    /**
    * Teams relative directory, with trailing slash.
    * 
    * @var mixed
    */
    protected $teams_relative_path = "var/teams/";

    /**
    * Database tables prefix.
    * 
    * @var mixed
    */
    protected $db_prefix = "pvpgn_";

    /**
    * Internal list of errors founded while parsing data.
    * 
    * @var mixed
    */
    protected $errors = array();

    /**
    * Internal variable with parsed data.
    * 
    * @var mixed
    */
    protected $parsed_data = array();

    /**
    * Class constructor.
    * 
    * @param mixed $options
    * @return pvpgn_plain_to_sql2
    */
    public function __construct($options) {
        // Bool variables
        $variables = array(
            "convert_users","convert_clans","convert_teams","sort_output_data","exit_on_first_error"
        );
        foreach($variables as $variable) {
            $this->$variable = (!isset($options[$variable])
                                        ? $this->$variable : ($options[$variable] ? true : false));
        }

        // Paths
        $variables = array(
            "pvpgn_absolute_path","users_relative_path","clans_relative_path","teams_relative_path"
        );
        foreach($variables as $variable) {
            if(!empty($options[$variable])) {
                // Check trailing slash
                $last_character = substr($options[$variable],-1);
                if(($last_character != "/") && ($last_character != "\\")) {
                    $options[$variable] .= "/";
                }
                $this->$variable = $options[$variable];
            }
        }

        // Others
        $variables = array(
            "time_limit","db_prefix"
        );
        foreach($variables as $variable) {
            $this->$variable = (empty($options[$variable]) ? $this->$variable : $options[$variable]);
        }

        // Time limit
        if($this->time_limit >= 0) {
            @ini_set("max_execution_time",($this->time_limit * 60));
        }

        // Memory limit
        if($this->time_limit >= 0) {
            @ini_set("memory_limit",($this->memory_limit * 1024 * 1024));
        }

        // Check for multibyte function
        if(!function_exists("mb_convert_encoding")) {
            $this->log_error("Multibyte function mb_convert_encoding() not exists");
        }
    }

    /**
    * Convert data and return output.
    * 
    * @param mixed $generate_raw_data
    * @return string
    */
    public function convert($generate_raw_data = false) {
        // Users
        if($this->convert_users) {
            $this->parse_directory("users");
        }

        // Clans
        if($this->convert_clans) {
            $this->parse_directory("clans");
        }

        // Teams
        if($this->convert_teams) {
            $this->parse_directory("teams");
        }

        // Output title
        $output = "/**\r\n"
                    . "* PvPGN plain to SQL2 format converter\r\n"
                    . "* http://naki.info/\r\n* \r\n"
                    . "* Copyright 2011 Naki (naki@pvpgn.pl). All rights reserved.\r\n"
                    . "*/\r\n\r\n";

        // Check for errors
        if(!empty($this->errors)) {
            $output .= "/**\r\n"
                        . "* !!! ERRORS FOUND !!! \r\n"
                        . "*/\r\n\r\n";
            foreach($this->errors as $error) {
                $output .= "/* ERROR: " . $error . ". */\r\n";
            }
            $output .= "\r\n";
        }

        // Output header
        $output .= "/**\r\n"
                    . "* OUTPUT \r\n"
                    . "*/\r\n\r\n";

        // Generate SQL queries
        $output .= $this->generate_sql_queries();

        // Prepare data for display in browser
        if(!$generate_raw_data) {
            $output = "<div style=\"white-space: nowrap\">\r\n"
                            . nl2br(htmlspecialchars($output,ENT_QUOTES))
                            . "</div>";
        }

        return $output;
    }

    /**
    * Get files list in directory.
    * 
    * @param mixed $directory
    * @return string
    */
    protected function read_directory($directory) {
        $results = array();
        if(@is_dir($directory)) {
            if($handle = @opendir($directory)) {
                while(false !== ($file = readdir($handle))) {
                    if(($file != ".") && ($file != "..")) {
                        $results[] = $file;
                    }
                }
                closedir($handle);
            }
            else {
                $this->log_error($directory . " - cannot open directory");
            }
        }
        else {
            $this->log_error($directory . " - is not directory");
        }
        return $results;
    }

    /**
    * Parse specified directory.
    * 
    * @param mixed $type
    */
    protected function parse_directory($type) {
        $relative_directory_path = ($type . "_relative_path");
        $directory = ($this->pvpgn_absolute_path . $this->$relative_directory_path);
        // Get directory files list
        $files = $this->read_directory($directory);
        if(!empty($files)) {
            // Choose method: parse_users_file(), parse_clans_file(), parse_teams_file()
            $function = ("parse_" . $type . "_file");
            foreach($files as $file) {
                $lines = @file($directory . $file);
                // Parse file
                $result = $this->$function($lines,$file);
                if(!empty($result)) {
                    $this->parsed_data[$type][] = $result;
                }
            }
        }
    }

    /**
    * Parse users file.
    * 
    * @param mixed $lines
    * @param mixed $file
    */
    protected function parse_users_file($lines,$file) {
        $results = array();
        $parsed_data = array();
        // Regular expression pattern
        $info_pattern = "/\"(?<var_type>.*?)\\\\\\\\(?<var_name>.*?)\"=\"(?<value>.*?)\"/s";
        if(!empty($lines)) {
            $id_user = 0;
            foreach($lines as $index => $line) {
                // User info
                if(preg_match($info_pattern,$line,$matches)) {
                    // Fix variable name
                    $matches['var_name'] = str_replace("\\\\","\\",$matches['var_name']);
                    // Not used (changed to arranged teams)
                    if($matches['var_type'] == "Team") {
                        continue;
                    }
                    // User identifier
                    elseif(($matches['var_type'] == "BNET") && ($matches['var_name'] == "acct\\userid")) {
                        $id_user = $matches['value'];
                    }
                    // User username
                    elseif(($matches['var_type'] == "BNET") && ($matches['var_name'] == "acct\\username")) {
                        $parsed_data[$matches['var_type']]['username'] = strtolower($matches['value']);
                    }
                    // Clean field
                    elseif(($matches['var_type'] == "BNET") && ($matches['var_name'] == "acct\\lastlogin_owner")) {
                        $matches['value'] = $this->clean_profile_fields($matches['value']);
                    }
                    // Clean profile fields
                    elseif($matches['var_type'] == "profile") {
                        $matches['value'] = $this->clean_profile_fields($matches['value']);
                    }
                    $parsed_data[$matches['var_type']][$matches['var_name']] = $matches['value'];
                }
                else {
                    $this->log_error(trim($line) . " - cannot parse line, "
                                        . "user file (" . $file . ") may be corrupted");
                }
            }
            // Prepare data for SQL generator
            if(!empty($id_user) && !empty($parsed_data)) {
                // Sort data
                if($this->sort_output_data) {
                    uksort($parsed_data,function($a,$b) {
                        return strcasecmp($a,$b);
                    });
                }
                foreach($parsed_data as $type => $data) {
                    if(!empty($data)) {
                        // Sort data
                        if($this->sort_output_data) {
                            ksort($data);
                        }
                        foreach($data as $name => $value) {
                            $results[$type][$name] = array(
                                'uid' => $id_user,
                                'name' => $name,
                                'value' => $value
                            );
                        }
                    }
                }
            }
        }
        return $results;
    }

    /**
    * Parse clans file.
    * 
    * @param mixed $lines
    * @param mixed $file
    */
    protected function parse_clans_file($lines,$file) {
        $results = array();
        // Regular expressions patterns
        $info_pattern = "/^\"(?P<name>.+)\",\"(?P<motd>.*)\",(?P<cid>[0-9]+),"
                            . "(?P<creation_time>[0-9]+)$/s";
        $member_pattern = "/^(?P<uid>[0-9]+),(?P<status>[0-9]+),(?P<join_time>[0-9]+)$/s";
        if(!empty($lines)) {
            $id_clan = 0;
            foreach($lines as $index => $line) {
                // Clan info
                if(!$index && preg_match($info_pattern,$line,$matches)) {
                    $id_clan = $matches['cid'];
                    $matches['short'] = $file;
                    $results = $matches;
                }
                // Clan members
                elseif($index && preg_match($member_pattern,$line,$matches)) {
                    if($id_clan) {
                        $matches['cid'] = $id_clan;
                        $results['members'][] = $matches;
                    }
                }
                else {
                    $this->log_error(trim($line) . " - cannot parse line, "
                                        . "clan file (" . $file . ") may be corrupted");
                }
            }
        }
        return $results;
    }

    /**
    * Parse teams file.
    * 
    * @param mixed $lines
    * @param mixed $file
    */
    protected function parse_teams_file($lines,$file) {
        $results = array();
        // Regular expressions patterns
        $info_pattern = "/^(?P<teamid>[0-9]+),(?P<size>[0-9]+),(?P<clienttag>[A-Z0-9]+),"
                            . "(?P<lastgame>[0-9]+)$/s";
        $members_pattern = "/^(?P<member1>[0-9]+),(?P<member2>[0-9]+),(?P<member3>[0-9]+),"
                            . "(?P<member4>[0-9]+)$/s";
        $stats_pattern = "/^(?P<wins>[0-9]+),(?P<losses>[0-9]+),(?P<xp>[0-9]+),(?P<level>[0-9]+),"
                            . "(?P<rank>[0-9]+)$/s";
        if(!empty($lines)) {
            foreach($lines as $index => $line) {
                // Team info
                if(!$index && preg_match($info_pattern,$line,$matches)) {
                    $results = $matches;
                }
                // Team members
                elseif(($index == 1) && preg_match($members_pattern,$line,$matches)) {
                    $results += $matches;
                }
                // Team stats
                elseif(($index > 1) && preg_match($stats_pattern,$line,$matches)) {
                    $results += $matches;
                }
                else {
                    $this->log_error(trim($line) . " - cannot parse line, "
                                        . "team file (" . $file . ") may be corrupted");
                }
            }
        }
        return $results;
    }

    /**
    * Generate SQL queries from parsed data.
    * 
    */
    protected function generate_sql_queries() {
        $result = "";
        // SQL structure columns
        $user_fields = array("uid","name","value");
        $clan_fields = array("cid","short","name","motd","creation_time");
        $clan_member_fields = array("uid","cid","status","join_time");
        $team_fields = array(
            "teamid","size","clienttag","lastgame",
            "member1","member2","member3","member4",
            "wins","losses","xp","level","rank"
        );
        if(!empty($this->parsed_data)) {
            // Sort data
            if($this->sort_output_data) {
                // Users
                if(!empty($this->parsed_data["users"])) {
                    uasort($this->parsed_data["users"],function($a,$b) {
                        $a = $a['BNET']['acct\\userid']['value'];
                        $b = $b['BNET']['acct\\userid']['value'];
                        return ($a - $b);
                    });
                }
                // Clans
                if(!empty($this->parsed_data["clans"])) {
                    uasort($this->parsed_data["clans"],function($a,$b) {
                        return ($a['cid'] - $b['cid']);
                    });
                }
                // Teams
                if(!empty($this->parsed_data["teams"])) {
                    uasort($this->parsed_data["teams"],function($a,$b) {
                        return ($a['teamid'] - $b['teamid']);
                    });
                }
            }
            // Generate queries
            foreach($this->parsed_data as $type => $entries) {
                if(!empty($entries)) {
                    $result .= "/* " . strtoupper($type) . " */\r\n";
                    foreach($entries as $entry) {
                        // Users
                        if($type == "users") {
                            if(!empty($entry)) {
                                foreach($entry as $table => $data) {
                                    $result .= $this->generate_sql_query($table,$user_fields,$data,false);
                                }
                                $result .= "\r\n";
                            }
                        }
                        // Clans
                        elseif($type == "clans") {
                            $result .= $this->generate_sql_query("clan",$clan_fields,$entry);
                            if(!empty($entry['members'])) {
                                $result .= $this->generate_sql_query(
                                    "clanmember",$clan_member_fields,$entry['members'],false
                                );
                            }
                            $result .= "\r\n";
                        }
                        // Teams
                        elseif($type == "teams") {
                            $result .= $this->generate_sql_query("arrangedteam",$team_fields,$entry);
                            $result .= "\r\n";
                        }
                    }
                }
            }
            // Connection encoding
            if(!empty($result)) {
                $result = ("SET NAMES `utf8`;\r\n\r\n" . $result);
            }
        }
        return $result;
    }

    /**
    * Generate SQL insert query.
    * 
    * @param mixed $table
    * @param mixed $variables
    * @param mixed $data
    * @param mixed $one_row
    */
    protected function generate_sql_query($table,$variables,$data,$one_row = true) {
        if(!empty($table) && !empty($variables) && !empty($data)) {
            // Convert to multidimensional array
            if($one_row) {
                $tmp_data = $data;
                $data = array();
                $data[] = $tmp_data;
            }
            // Generate values data
            $values = array();
            foreach($data as $entry) {
                $info = array();
                foreach($variables as $variable) {
                    $info[] = (isset($entry[$variable])
                                ? $this->escape_string_for_query($entry[$variable])
                                : "");
                }
                $values[] = "('" . implode("','",$info) . "')";
            }
            // Generate query
            $result = "INSERT INTO `" . $this->db_prefix . $table . "`"
                        . "(`" . implode("`,`",$variables) . "`) "
                        . "VALUES "
                        . implode(",",$values) . ";\r\n";
        }
        return $result;
    }

    /**
    * Escape string for SQL query.
    * 
    * @param mixed $text
    * @return mixed
    */
    protected function escape_string_for_query($text) {
        return str_replace(
            array("\\","\0","\n","\r","'","\"","\x1a"),
            array("\\\\","\\0","\\n","\\r","\\'","\\\"","\\Z"),
            $text
        );
    }

    /**
    * Log error message.
    * 
    * @param mixed $message
    */
    protected function log_error($message) {
        // Exit on first error
        if($this->exit_on_first_error) {
            die("Error occurred: " . $message . ".");
        }
        // Add error to array
        $this->errors[] = $message;
    }

    /**
    * Clean profile fields managed by users.
    * 
    * @param mixed $text
    * @return string
    */
    protected function clean_profile_fields($text) {
        // New lines
        $text = str_replace("\\r\\n","\r\n",$text);
        // Convert ASCII to UTF-8 encoding
        $text = preg_replace_callback("/(\\\\+)([0-9]{3})/s",array("self","rewrite_ascii"),$text);
        // Change `\\` to `\` and `\"` to `"`
        $text = str_replace(array("\\\\","\\\""),array("\\","\""),$text);
        // Convert HTML entities to characters
        return html_entity_decode($text,ENT_QUOTES);
    }

    /**
    * Convert ASCII codes to UTF-8 encoding.
    * 
    * @param mixed $matches
    */
    protected function rewrite_ascii($matches) {
        // Check `\` characters before ASCII code
        $strlen = strlen($matches[1]);
        if(!($strlen % 2)) {
            return ($matches[1] . $matches[2]);
        }
        return (($strlen > 1 ? substr($matches[1],1) : "")
                    . (function_exists("mb_convert_encoding")
                        ? mb_convert_encoding(chr(octdec($matches[2])),"UTF-8","ASCII")
                        : chr(octdec($matches[2]))));
    }
}