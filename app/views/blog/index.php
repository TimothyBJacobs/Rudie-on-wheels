
<h1><?=$this->title('Posts')?> <smaller>(<a href="<?=$this::url('blog/add_post')?>">new</a>)</smaller></h1>

<p>Showing <?=count($posts)?> newest of <?=$allPosts?> posts...</p>

<?foreach( $posts as $post ):?>

	<article class="blogpostpreview <?=!$post->is_published ? 'unpublished' : ''?>">
		<footer>Posted by <em><?=$post->author->full_name?></em> on <em utc="<?=$post->created_on?>"><?=$post->_created_on->format('Y-m-d H:i:s')?></em>.</footer>
		<header><h2><a href="<?=$post->url()?>"><?=$post->title?></a></h2></header>
		<?=$this::markdown($post->body)."\n"?>
		<footer>In <?=$this::link($post->category_name, $post->catUrl())?> | <a href="<?=$post->url('#comments')?>"><?=count($post->comments)?> comments</a></footer>
	</article>

<?endforeach?>

<!-- <pre>
<? print_r($posts) ?>
</pre> -->
