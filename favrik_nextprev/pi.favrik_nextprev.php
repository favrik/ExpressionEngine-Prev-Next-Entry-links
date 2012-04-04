<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * favrik Next/Prev entry linking
 *
 * REQUIRES ExpressionEngine 2+
 *
 * @package     favrik_nextprev
 * @version     1.0.0
 * @author      Favio Manriquez Leon (Favrik)
 * @copyright   Copyright (c) 2011 Favrik
 * @license     http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 * 
 */
 
$plugin_info = array(   'pi_name'           => 'EE2 favrik Next/Prev entry linking',
                        'pi_version'        => '1.0.1',
                        'pi_author'         => 'Favrik',
                        'pi_author_url'     => 'https://github.com/favrik/ExpressionEngine-Prev-Next-Entry-links',
                        'pi_description'    => 'Outputs next/prev entry links',
                        'pi_usage'          => Favrik_nextprev::usage());

/**
 * Favrik_nextprev For EE2.0
 *
 * @package     favrik_nextprev
 * @author      Favrik
 * @version     1.0.0
 */
Class Favrik_nextprev
{

    function __construct()
    {
        $this->EE =& get_instance();

        $this->id = $this->EE->TMPL->fetch_param('id', FALSE);
        $this->id = $this->id === '' ? FALSE : $this->id;

        $this->channel = $this->EE->TMPL->fetch_param('channel', FALSE);
        
        // Check for a category ID
        $this->category_id = $this->EE->TMPL->fetch_param('category_id', FALSE);
        $this->category_id = $this->category_id === '' ? FALSE : $this->category_id;

        if ( ! $this->is_entry_cached() )
        {
            $entry_id = $this->set_current_entry();
        }
    }

    function is_entry_cached()
    {
        if (
            ! isset($this->EE->session->cache['channel']['single_entry_id'])
            OR
            ! isset($this->EE->session->cache['channel']['single_entry_date'])
        )
        {
            return FALSE;
        }

	    return TRUE;
    }

    function set_current_entry()
    {
        if (FALSE === $this->id)
        {
            return;
        }

        $row = $this->_find_current_entry_id();
        if (FALSE === $row)
        {
            $this->id = FALSE;
        }
        else
        {
    	    $this->EE->session->cache['channel']['single_entry_id'] = $row['entry_id'];
	        $this->EE->session->cache['channel']['single_entry_date'] = $row['entry_date'];
        }
    }

    function _find_current_entry_id()
    {
        $sql = 'SELECT t.entry_id, t.entry_date
                FROM (exp_channel_titles AS t)
                LEFT JOIN exp_channels AS w ON w.channel_id = t.channel_id ';

        $entry_type = is_numeric($this->id) ? 'entry_id' : 'url_title';

        $sql .= " WHERE t.$entry_type = '".$this->EE->db->escape_str($this->id)."' ";
        $sql .= " AND w.site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') ";

        if ($this->channel !== FALSE)
        {
            $sql .= $this->EE->functions->sql_andor_string($this->channel, 'channel_name', 'w');
        }

        $query = $this->EE->db->query($sql);
        if ($query->num_rows() != 1)
        {
            $this->EE->TMPL->log_item('Favrik Next/Prev Entry tag error: Could not find entry id.');
            return FALSE;
        }

        return $query->row_array();
    }

    function prev_entry()
    {
        return $this->_get_entry('prev');
    }

    function next_entry()
    {
        return $this->_get_entry('next');
    }

    function _get_entry($type)
    {
        if (FALSE === $this->id)
        {
            return '';
        }

        $sql = 'SELECT t.entry_id, t.title, t.url_title, t.entry_date, w.channel_name
                FROM (exp_channel_titles AS t)
                LEFT JOIN exp_channels AS w ON w.channel_id = t.channel_id ';
		
		// If a category ID is present, limit next/prev
        //    to just entries within this category
		if ($this->category_id !== FALSE) {                
			$sql .= 'LEFT JOIN exp_category_posts AS ecp ON ecp.entry_id = t.entry_id ';
		}

        $sql .= ' WHERE t.entry_id != '.$this->EE->session->cache['channel']['single_entry_id'].' ';
        
        // If a category ID is present, limit next/prev
        //    to just entries within this category
        if ($this->category_id !== FALSE) {
        	$sql .= ' AND ecp.entry_id = t.entry_id AND ecp.cat_id = ' . $this->category_id;
        }
       
        $comparison = ($type === 'next') ? '>' : '<';
	    $sort = ($type === 'next') ? 'ASC' : 'DESC';

        $sql .= " AND t.entry_date $comparison= " . $this->EE->session->cache['channel']['single_entry_date'].' ';
        $sql .= ' AND IF (t.entry_date = '
            . $this->EE->session->cache['channel']['single_entry_date']
            . ", t.entry_id $comparison " . $this->EE->session->cache['channel']['single_entry_id'].', 1) ';

        $sql .= " AND w.site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') ";

        if ($this->channel !== FALSE)
		{
			$sql .= $this->EE->functions->sql_andor_string($this->channel, 'channel_name', 'w')." ";
        }

        if ($status = $this->EE->TMPL->fetch_param('status'))
        {
            $status = str_replace('Open',   'open',   $status);
            $status = str_replace('Closed', 'closed', $status);

            $sql .= $this->EE->functions->sql_andor_string($status, 't.status')." ";
        }
        else
        {
            $sql .= "AND t.status = 'open' ";
        }

        $sql .= " ORDER BY t.entry_date {$sort}, t.entry_id {$sort} LIMIT 1";

        $query = $this->EE->db->query($sql);

        if ($query->num_rows() == 0)
        {
            return;
        }

        return $this->_replace_vars($query);
    }

    function _replace_vars($entry)
    {
        $query = $entry;


        // Check if date is in the future!
        $now_localized        = $this->EE->localize->set_localized_time();
        $entry_date_localized = $this->EE->localize->set_localized_time($query->row('entry_date'));
        if ($entry_date_localized > $now_localized)
        {
            return ''; // Empty content
        }


        $this->EE->load->library('typography');

        if (strpos($this->EE->TMPL->tagdata, LD.'url_title') !== FALSE)
        {
            $this->EE->TMPL->tagdata = str_replace(LD.'url_title'.RD, $query->row('url_title'), $this->EE->TMPL->tagdata);
        }

        if (strpos($this->EE->TMPL->tagdata, LD.'entry_id') !== FALSE)
        {
            $this->EE->TMPL->tagdata = str_replace(LD.'entry_id'.RD, $query->row('entry_id'), $this->EE->TMPL->tagdata);
        }

        if ( (strpos($this->EE->TMPL->tagdata, LD.'entry_date') !== FALSE)
              &&
              preg_match_all("/".LD.'entry_date'."\s+format=([\"'])([^\\1]*?)\\1".RD."/s", $this->EE->TMPL->tagdata, $matches)
        )
        {
            for ($j = 0; $j < count($matches[0]); $j++)
            {
                $matches[0][$j] = str_replace(array(LD,RD), '', $matches[0][$j]);
                $params = $this->EE->localize->fetch_date_params($matches[2][$j]);
                $replace = $this->EE->localize->convert_timestamp($params, $query->row('entry_date'), TRUE);
                $value = str_replace($params, $replace, $matches[2][$j]);
                $this->EE->TMPL->tagdata = str_replace(LD.$matches[0][$j].RD, $value, $this->EE->TMPL->tagdata);
            }
        }

        if (strpos($this->EE->TMPL->tagdata, LD.'channel_name') !== FALSE)
        {
            $this->EE->TMPL->tagdata = str_replace(LD.'channel_name'.RD, $query->row('channel_name'), $this->EE->TMPL->tagdata);
        }

        if (strpos($this->EE->TMPL->tagdata, LD.'title') !== FALSE)
        {
            $this->EE->TMPL->tagdata = str_replace(LD.'title'.RD, $this->EE->typography->format_characters($query->row('title')), $this->EE->TMPL->tagdata);
        }

        return $this->EE->functions->remove_double_slashes(stripslashes($this->EE->TMPL->tagdata));
    }

    static function usage()
    {
    	ob_start();
	?>


Usage Example
----------
{exp:favrik_nextprev:next_entry id="{segment_2}"}
        <a href="{site_url}{channel_short_nane}/{entry_date format="%Y/%m/%d"}/{url_title}">{title}</a>
{/exp:favrik_nextprev:next_entry}
	<?php
	$buffer = ob_get_contents();
	
	ob_end_clean(); 

	return $buffer;
        
    }


}
