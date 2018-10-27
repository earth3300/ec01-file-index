<?php

/**
 * EC01 File Index.
 *
 * Creates a listing of files and folders in the directory in which it is placed.
 * This listing is displayed using HTML. It can then be viewed as is, or copied and
 * pasted elsewhere, as needed. This file does not display images or other media.
 *
 * @package EC01 File Index
 * @since 1.0.0
 * @author Clarence Bos <cbos@tnoep.ca>
 * @copyright Copyright (c) 2018, Clarence Bos
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPL-3.0+
 * @link https://github.com/earth3300/ec01-file-index

 * @wordpress-plugin
 * Plugin Name: EC01 File Index
 * Plugin URI:  https://github.com/earth3300/ec01-file-index
 * Description: Creates a listing of files and folders in the directory in which it is placed. This listing is displayed using HTML. It can then be viewed as is, or copied and pasted elsewhere, as needed. This file does not display images or other media. Shortcode [file-index dir=""].
 * Version: 1.0.0
 * Author: Earth3300
 * Author URI: https://github.com/earth3300
 * Text Domain: ec01-media-index
 * License:  GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 */

/**
 * Creates a listing of files and folders in the directory in which it is placed.
 *
 * See the bottom of this file for the switch for determining the context in which
 * this file is found.
 */
class FileIndex
{
	/** @var array Default options. */
	protected $opts = [
		'dim' => [ 'width' => 800, 'height' => 600 ],
		'max' => 50,
		'msg' => [ 'na' => '', ],
		'subtypes' => [
			'.*' => [ 'type' => 'directory' ],
			'.html' => [ 'type' => 'html' ],
			'.png' => [ 'type' => 'image' ],
			],
		'supertype' => 'file',
	];

	/**
	 * Gets the list files and directories as HTML.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function get( $args = null )
	{
		/** If no arguments are set, assume current directory */
		if ( $args = $this->setDirectorySwitch( $args ) )
		{
			$args['path'] = $this->getBasePath( $args );
			$max = $this->getMaxItems( $args );
			$subtypes = $this->opts['subtypes'];

			/** Add the "supertype" to the class string (i.e. media). */
			$args['class'] = $this->opts['supertype'];

			$str = '<article>' . PHP_EOL;

			/** If the class is not already present, add it. */
			if( strpos( $args['class'], $args['type'] ) === FALSE )
			{
				$args['class'] .= ' ' . $args['type'];
			}

			$str .= $this->iterateDirectory( $match, $max, $args );
			$str .= '</article>' . PHP_EOL;

			if ( isset( $args['doctype'] ) && $args['doctype'] )
			{
				$str = $this->getPageHtml( $str, $args );
			}
			return $str;
		}
		else
		{
			return "Error.";
		}
	}

	/**
	 * Iterate Directory.
	 *
	 * Capability for html, png, and a directory.
	 *
	 * @param string $match
	 * @param array $args
	 *
	 * @return string
	 */
	private function iterateDirectory( $match, $max, $args )
	{
		$str = '';
		$cnt = 0;
		$reader = new DirectoryReader( $args['path'] );
		$dir = $reader->getListing();
		$str = '<ol>' . PHP_EOL;
		foreach ( $dir['directories'] as $file )
		{
			$str .= sprintf( '<li><a href="%s/">%s</a></li>%s', $file['fileName'], $this->ucAsNeeded( $file['fileName'] ), PHP_EOL );
		}
		$str .= '</ol>' . PHP_EOL;
		return $str;
	}

	/**
	 * Upper Case As Needed.
	 *
	 * Convert to uppercase if first letter or if all non-vowels and three letters or greater.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	private function ucAsNeeded( $str )
	{
		// check for any vowels
		$regex = '/([aeiou])/';
		preg_match( $regex, $str, $match );

		// If there are NO voweols and the string length is less than, say, seven
		// it is probably an acronym. If there are only two letters, it is proably
		// an acronym.
		if (
			 ( strlen( $str ) < 3 )
			 ||
			 ( ! isset( $match[0] ) && strlen( $str ) <= 7 )
		   )
		{
			$converted = strtoupper( $str );
		}
		// else, it contains vowels and is of a reasonable length.
		else {
			$converted = ucfirst( $str );
		}
		return $converted;
	}

	/**
	 * Iterate over files.
	 *
	 * Capability for html, png, and a directory.
	 *
	 * @param string $match
	 * @param array $args
	 *
	 * @return string
	 */
	private function iterateFiles( $match, $max, $args )
	{
		$str = '';
		$cnt = 0;
		foreach ( glob( $match ) as $file )
		{
			$cnt++;
			if ( $cnt > $max )
			{
				break;
			}
			$args['file'] = $file;
			/** Remove the root of the file path to use it an item source. */
			$args['src'] = $this->getSrcFromFile( $args['file'] );
			$args['name'] = $this->getNameFromSrc( $args['src'] );
			$str .= $this->getItemHtml( $args );
		}
		return $str;
	}

	/**
	 * Get the source from the file, checking for a preceding slash.
	 *
	 * @param string $str
	 * @return string
	 */
	private function getSrcFromFile( $str )
	{
		$src = str_replace( $this->getSitePath(), '', $str );
		/** May be server inconsistency, therefore remove and add again. */
		$src = ltrim( $src, '/' );
		return '/' . $src;
	}

	/**
	 * Get the name from the source, adjusting as necessary
	 *
	 * @param string $str
	 * @return string
	 */
	private function getNameFromSrc( $str )
	{
		$arr = explode( '/', $str );
		$name = $arr[ count( $arr ) - 1 ];
		return $name;
	}

	/**
	 * Get the SITE_PATH
	 *
	 * Get the SITE_PATH from the constant, from ABSPATH (if loading within WordPress
	 * as a plugin), else from the $_SERVER['DOCUMENT_ROOT']
	 *
	 * Both of these have been tested online to have a preceding forward slash.
	 * Therefore do not add one later.
	 *
	 * @return bool
	 */
	private function getSitePath()
	{
		if ( defined( 'SITE_PATH' ) )
		{
			return SITE_PATH;
		}
		/** Available if loading within WordPress as a plugin. */
		elseif( defined( 'ABSPATH' ) )
		{
			return ABSPATH;
		}
		else
		{
			return $_SERVER['DOCUMENT_ROOT'];
		}
	}

	/**
	 * Get the maximum number of images to process.
	 *
	 * @param array $args
	 * @return int
	 */
	private function getMaxItems( $args )
	{
		if ( isset( $args['max'] ) )
		{
			$max = $args['max'];
		}
		else
		{
			$max = $this->opts['max'];
		}
		return $max;
	}

	/**
	 * Build the match string.
	 *
	 * This is iterated through for each type added to $types, above. A basic
	 * check for a reasonable string length (currently 10) is in place. Can
	 * develop this further, if needed.
	 *
	 * @param string $type  'html', 'png', '.', '..'
	 * @param array $args
	 *
	 * @return string|false
	 */
	private function getMatchPattern( $type, $args )
	{
		$prefix = "/*";
		$match =  $args['path'] . $prefix . $type;
		$match = $args['path'] . "/*.*";
		/** Very basic check. Can improve, if needed. */
		if ( strlen( $match ) > 5 )
		{
			return $match;
		}
		else {
			return false;
		}
	}

	/**
	 * Get the Base Path to the Media Directory.
	 *
	 * This does not need to include the `/media` directory.
	 *
	 * @param array $args
	 * @return string
	 */
	private function getBasePath( $args )
	{
		if ( isset( $args['self'] ) )
		{
			$path = __DIR__;
		}
		elseif ( defined( 'SITE_CDN_PATH' ) )
		{
			$path = SITE_CDN_PATH;
		}
		return $path;
	}

	/**
	 * Get the Item Directory to Process.
	 *
	 * @param array $args
	 *
	 * @return string
	 *
	 * @example $args['dir'] = '/wki/comp/linux/'
	 */
	private function getItemDir( $args )
	{
		if ( isset( $args['dir'] ) )
		{
			// Process the directory given. Could include a check here.
			$dir = $args['dir'];
		}
		else
		{
			// An empty string means: process the current directory.
			$dir = '';
		}
		return $dir;
	}

	/**
	 * Get the Item HTML.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	private function getItemHtml( $args )
	{
		/*
		echo "<pre>";
		var_dump( $args );
		echo "</pre>";
		*/
		//return $str;
	}

	/**
	 * Wrap the string in page HTML `<!DOCTYPE html>`, etc.
	 *
	 * @param string $str
	 * @return string
	 */
	public function getPageHtml( $html, $args )
	{
		$str = '<!DOCTYPE html>' . PHP_EOL;
		$str .= sprintf( '<html class="dynamic %s" lang="en-CA">', $args['type'], PHP_EOL );
		$str .= '<head>' . PHP_EOL;
		$str .= '<meta charset="UTF-8">' . PHP_EOL;
		$str .= '<meta name="viewport" content="width=device-width, initial-scale=1"/>' . PHP_EOL;
		$str .= '<title>File Index</title>' . PHP_EOL;
		$str .= '<meta name="robots" content="noindex,nofollow" />' . PHP_EOL;
		$str .= '<link rel=stylesheet href="/0/theme/css/style.css">' . PHP_EOL;
		$str .= '</head>' . PHP_EOL;
		$str .= '<body>' . PHP_EOL;
		$str .= '<main>' . PHP_EOL;
		$str .= $html;
		$str .= '</main>' . PHP_EOL;
		$str .= '<footer>' . PHP_EOL;
		$str .= '<div class="text-center"><small>';
		$str .= 'Note: This page has been <a href="https://github.com/earth3300/ec01-file-index">automatically generated</a>. No header, footer, menus or sidebars are available.';
		$str .= '</small></div>' . PHP_EOL;
		$str .= '</footer>' . PHP_EOL;
		$str .= '</html>' . PHP_EOL;

		return $str;
	}

	/**
	 * Get the item size
	 *
	 * @param array $args
	 *
	 * @return string|null
	 */
	private function getItemSize( $args ){

		if ( isset( $args['file'] ) )
		{
			$size = filesize( $args['file'] );
			$size = number_format( $size / 1000, 1, ".", "," );
			return $size . ' kB';
		}
		else {
			return null;
		}
	}

	/**
	 * Get the Item Name
	 *
	 * @param array $args
	 * @return string
	 */
	private function getItemName( $str )
	{
		if ( strlen( $str ) > 2 )
		{
			$name = str_replace( '-', ' ', $str );
			$name = strtoupper( $name );
		}
		else
		{
			$name = $this->opts['msg']['na'];
		}
		return $name;
	}


	/**
	 * Get the image class.
	 *
	 * @param array $args
	 * @return string
	 */
	private function getImageClass( $args )
	{
		if ( defined( 'SITE_IMAGE_CLASS' ) )
		{
			$class = SITE_IMAGE_CLASS;
		}
		else
		{
			$class = 'generic';
		}
		return $class;
	}

	/**
	 * Set the Directory Switch (Process Containing or Given Directory).
	 *
	 * If $args['self'] or $args['dir'] are not set, it assumes we are in the
	 * directory for which images are to be processed. Therefore $args['self']
	 * is set to true and $args['dir'] is set to null. We also have to set the
	 * $args['doctype'] to true to know whether or not to wrap the output in
	 * the correct doctype and the containing html and body elements.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	private function setDirectorySwitch( $args )
	{
		/** If $args['dir'] is not set, set it to false. */
		$args['dir'] = isset( $args['dir'] ) ? $args['dir'] : false;

		/** if $args['dir'] == false, set $args['self'] to true. */
		if ( ! $args['dir'] )
		{
			$args['self'] = true;
			$args['doctype'] = true;
			return $args;
		}
		else
		{
			return $args;
		}
	}
}

/**
 * Directory Reader Class
 *
 * Provides detailed information about the directory, including permissions, file size, etc.
 * @link  https://codereview.stackexchange.com/questions/29691/retrieving-all-directorie-names-relative-path
 */
class DirectoryReader
{
	/** @var array $directory */
    private $directory;

	/** @var array $listing */
    private $listing;

	/**
	 * Construct
	 */
    public function __construct($directory)
	{
        try {
            $this->directory = $directory;
            $this->listing = array();
            $this->listDir();
        }
		catch(UnexpectedValueException $e)
		{
            die("Path cannot be opened.");
        }
		catch(RuntimeException $e)
		{
            die("Path given is empty string.");
        }
    }

	/**
	 * Get the lisiting.
	 *
	 * @param void
	 *
	 * @return array
	 */
    public function getListing()
	{
        return $this->listing;
    }

	/**
	 * List the Directories (Non Recursively).
	 *
	 * @param void
	 *
	 * @return array
	 */
    private function listDir()
	{
        foreach ( new DirectoryIterator($this->directory) as $path )
		{
            if($path->isDir())
			{
                $cache = $this->getInfoArray($path->__toString());
                isset($cache) ? $this->listing['directories'][] = $cache : "";
                unset($cache);
            } else
			{
                $this->listing['files'][] = $this->getInfoArray($path->__toString());
            }
        }
    }

	/**
	 * List the Directories Recursively.
	 *
	 * @param void
	 *
	 * @return array
	 */
    private function listDirRecursive()
	{
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $path)
		{
            if($path->isDir())
			{
                $cache = $this->getInfoArray($path->__toString());
                isset($cache) ? $this->listing['directories'][] = $cache : "";
                unset($cache);
            } else
			{
                $this->listing['files'][] = $this->getInfoArray($path->__toString());
            }
        }
    }

	/**
	 * Get the Info Array.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
    private function getInfoArray($path)
	{
        $d = new SplFileInfo($path);
        if($d->getBasename() == "." || $d->getBasename() == "..")
		{
            return;
        } else {
            return array(
               "pathName"    => $d->getPathname(),
               "access"      => $d->getATime(),
               "modified"    => $d->getMTime(),
               "permissions" => $d->getPerms(),
               "size"        => $d->getSize(),
               "type"        => $d->getType(),
               "path"        => $d->getPath(),
               "baseName"    => $d->getBasename(),
               "fileName"    => $d->getFilename()
            );
        }
    }
}

/**
 * Callback from the file-index shortcode.
 *
 * Performs a check, then instantiates the FileIndex class
 * and returns the file list as HTML.
 *
 * @param array  $args['dir']
 * @return string  HTML as a list of images, wrapped in the article element.
 */
function file_index( $args )
{
	if ( is_array( $args ) )
	{
		$file_index = new FileIndex();
		return $file_index -> get( $args );
	}
	else
	{
		return '<!-- Missing the directory to process. [file-index dir=""]-->';
	}
}

/**
 * Check context (WordPress Plugin File or Directory Index File).
 *
 * The following checks to see whether or not this file (index.php) is being loaded
 * as part of the WordPress package, or not. If it is, we expect a WordPress
 * function to be available (in this case, `add_shortcode`). We then ensure there
 * is no direct access and add the shortcode hook, `media-index`. If we are not in
 * WordPress, then this file acts as an "indexing" type of file by listing all
 * of the allowed media types (currently jpg, png, mp3 and mp4) and making them
 * viewable to the end user by wrapping them in HTML and making use of a css
 * file that is expected to be found at `/0/media/theme/css/style.css`. This
 * idea was developed out of work to find a more robust method to develop out a
 * site, including that for a community. It makes use of the package found at:
 * {@link https://github.com/earth3300/ec01/wiki/}, with the entire codeset
 * available there through the same link.
 */
if( function_exists( 'add_shortcode' ) )
{
	// No direct access.
	defined('ABSPATH') || exit('No direct access.');

	//shortcode [file-index dir=""]
	add_shortcode( 'file-index', 'file_index' );
}
else
{
	/**
	 * Outside of WordPress. Instantiate directly, assuming current directory.
	 *
	 * @return string
	 */
	$file_index = new FileIndex();
	echo $file_index -> get();
}

function pre_dump( $arr ) {
	if( 1 )
	{
		echo "<pre>";
		var_dump( $arr );
		echo "</pre>";
	}
}
