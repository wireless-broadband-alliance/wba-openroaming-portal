## Asset Compilation (Tailwind CSS)

### For Production

Run the following commands to compile and minify assets:

```sh
php bin/console tailwind:build --minify
php bin/console asset-map:compile
```

### For Development

Use the watch mode for Tailwind CSS:

```sh
php bin/console tailwind:build -w &
```

> __Tip:__
> If changes to your assets aren't visible after running `asset-map:compile`, try clearing the `public/assets/` folder:
>
> ```sh
> rm -rf public/assets/*
> ```
>
> This allows Symfony to serve assets dynamically again.

---

## Icons & Color Customization

To change icon colors:

1. Remove any __stroke__ or __fill__ attributes from the SVG file.

2. Recompile the assets:

```sh
php bin/console asset-map:compile
```

---

> __Tip:__
> If changes to your assets aren't visible after running `asset-map:compile`, try clearing the `assets/vendor/` folder:
>
> ```sh
> rm -rf assets/vendor/*
> ```
>
> This allows Symfony to serve assets dynamically again.