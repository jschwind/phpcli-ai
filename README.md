# PHPCLI-AI

Generate a text-based dump of source files or a directory tree structure, suitable for AI processing.

## Installation

```shell
git clone https://github.com/jschwind/phpcli-ai.git
cd phpcli-ai
chmod +x runAIProject.sh
````

Add `runAIProject.sh` to your PATH or create a symlink, e.g., on Arch/Manjaro Linux via `~/.bashrc`:

```shell
sudo ln -s $(pwd)/runAIProject.sh /usr/local/bin/runAIProject
```

## Usage

```shell
runAIProject [OUTPUT_FILENAME] [--tree]
```

Generates a dump of relevant project files or a directory tree. By default, the output is written to `ai.txt` in the current directory.

### Options

* `OUTPUT_FILENAME`: Optional. Name of the output file (default: `ai.txt`)
* `--tree`: Optional. Generates a directory tree instead of a file dump

## ai.json Support

You can customize which files and folders are excluded from the output by placing an `ai.json` file in the root of your project. This overrides the default ignore rules.

### Example `ai.json`:

```json
{
  "ignoreExtensions": ["log", "png", "jpg"],
  "ignoreFolders": ["vendor", "node_modules", "tests"],
  "ignoreFilenames": ["README.md", ".env"]
}
```

### Priority

If `ai.json` is present and readable in the target directory, its values will be used instead of the default ignore lists.

## Examples

```shell
runAIProject
```

Dumps source content into `ai.txt`.

```shell
runAIProject ai2.txt
```

Same as above, output goes to `ai2.txt`.

```shell
runAIProject --tree
```

Generates a directory tree and saves it to `ai.txt`.

```shell
runAIProject ai2.txt --tree
```

Generates a directory tree and saves it to `ai2.txt`.
