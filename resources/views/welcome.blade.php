<form method="POST" action="/compile">
    @csrf
    <textarea name="code" rows="6" cols="50"></textarea><br>
    <button type="submit">Compile</button>
</form>
