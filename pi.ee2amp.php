<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Author ID Plugin
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		Willow Light Studio
 * @link		https://willowlightstudio.com
 */

$plugin_info = array(
	'pi_name'		=> 'AMP Parser for ExpressionEngine',
	'pi_version'	=> '1.0',
	'pi_author'		=> 'Willow Light Studio',
	'pi_author_url'	=> 'https://willowlightstudio.com',
	'pi_description'=> 'Makes HTML tags AMP compatible',
	'pi_usage'		=> ee2amp::usage()
);

require 'vendor/autoload.php';
use PHPHtmlParser\Dom;

class ee2amp {



	protected $return_data;
    
	public function __construct()
	{
	}

	function parse() 
	{

		$vars = array();

		$data = ee()->TMPL->fetch_data_between_var_pairs(ee()->TMPL->tagdata, 'content');

		$min_width = ee()->TMPL->fetch_param('min_width') ?: 480;

		$cond['vimeo'] = 'no';
		$cond['soundcloud'] = 'no';
		$cond['youtube'] = 'no';

		$dom = new Dom;

		$dom->load($data);

		// Replaces multiple whitespaces in image tags if present

		$data = preg_replace_callback('/<img.*?\/?>/', function ($matches) { return preg_replace('!\s+!', ' ', $matches[0]) ; }, $data);
		
		// Replace images

		$images = $dom->find('img');
		foreach ($images as $img)
		{
			$img_width = $img->getAttribute('width') ?: NULL;
			$img_height = $img->getAttribute('height') ?: NULL;
			$img_alt = $img->getAttribute('alt') ?: NULL;
			$img_src = $img->getAttribute('src') ?: NULL;

			if ($img_width === NULL OR $img_height === NULL)
			{	


				$styles = $img->getAttribute('style');
				$styles = rtrim($styles, ';');
				$styles = explode(";", $styles);			
				foreach ($styles as $style)
				{
					if (strpos($style, 'width') !== FALSE AND strpos($style,'-width') === FALSE)
					{
						$img_width = trim(substr($style, strpos($style, ':')+1, -2));
					}

					if (strpos($style, 'height') !== FALSE AND strpos($style,'-height') === FALSE)
					{
						$img_height = trim(substr($style, strpos($style, ':')+1, -2));
					}

				}
			}
			
			$orig_img = $img->outerHtml();
			
			if ($img_width && $img_height)
			{
				$new_img = '<amp-img src="' . $img_src . '"';
				if ($img_alt) $new_img .= ' alt="' . $img_alt . '"';
				if ($img_width) $new_img .= ' width="' . $img_width . '"';
				if ($img_height) $new_img .= ' height="' . $img_height . '"';	
				if ($img_width < $min_width)
				{
					$new_img .= ' layout="fixed"></amp-img>';				
				}
				else
				{
					$new_img .= ' layout="responsive"></amp-img>';				
				}
				
				$data = preg_replace('/ ?\/>/', '>', $data);
				$data = $this->str_replace_first($data, $orig_img, $new_img);

			}
			
			else { $data = $this->str_replace_first($data, $orig_img, ''); }

		}

		// Replace iframes

		$iframes = $dom->find('iframe');

		foreach ($iframes as $iframe)
		{
			// Vimeo

			if (strpos($iframe->getAttribute('src'), 'player.vimeo.com') !== FALSE)
			{
				$cond['vimeo'] = 'yes';
				$video_src = substr($iframe->getAttribute('src'), strrpos($iframe->getAttribute('src'), '/') + 1);
				$video_width = $iframe->getAttribute('width');
				$video_height = $iframe->getAttribute('height');
				$new_video = "<amp-vimeo data-videoid='{video_src}' width='{$video_width}' height='{$video_height}' layout='responsive'></amp-vimeo>";
				$data = $this->str_replace_first($data, $iframe->outerHtml(), $new_video);
			}

			// YouTube

			if (strpos($iframe->getAttribute('src'), 'youtube.com') !== FALSE)
			{
				$cond['youtube'] = 'yes';
				$video_src = substr($iframe->getAttribute('src'), strrpos($iframe->getAttribute('src'), '/embed/') + 7);
				if (strpos($video_src, '?') !== FALSE)
				{
					$video_src = substr($video_src, 0, strpos($video_src, '?'));
				}
				$video_width = $iframe->getAttribute('width');
				$video_height = $iframe->getAttribute('height');
				$new_video = "<amp-youtube data-videoid='{$video_src}' width='{$video_width}' height='{$video_height}'' layout='responsive'></amp-youtube>";
				$data = $this->str_replace_first($data, $iframe->outerHtml(), $new_video);

			}			
			
			// Soundcloud
			if (strpos($iframe->getAttribute('src'), 'soundcloud.com') !== FALSE)
			{
				$cond['soundcloud'] = 'yes';
				$sound_src = substr($iframe->getAttribute('src'), strpos($iframe->getAttribute('src'), '/tracks/') + 8);
				$sound_src = substr($sound_src, 0, strpos($sound_src, 'a') -1);
				$sound_height = $iframe->getAttribute('height');
				$new_sound = "<amp-soundcloud data-trackid='{$sound_src}' height='{$sound_height}' layout='fixed-height' data-visual='true'></amp-soundcloud>";
				$data = $this->str_replace_first($data, $iframe->outerHtml(), $new_sound);				
			}

		}
		
		// Remove any inline styles

		$data = preg_replace('/(<[^>]+) (style|shape)=".*?"/', '$1', $data);

		// Remove scripts, objects and iframes

		$data = preg_replace('/<(script|object|iframe).*?>.*?<\/\1>/', '', $data);

		$data = ee()->functions->prep_conditionals($data, $cond);

		return $data;
	}	


	function str_replace_first($string ,$search , $replace) 
	{ 
		if ((($string_len=strlen($string))==0) || (($search_len=strlen($search))==0)) return $string;
		$pos=strpos($string,$search);
		if ($pos>0) return substr($string,0,$pos).$replace.substr($string,$pos+$search_len,max(0,$string_len-($pos+$search_len)));
		return $string;
	}

	public static function usage()
	{
		ob_start();
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
}