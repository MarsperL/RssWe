<div align="center">


  # rsswe

</div>


无需服务器的RSS和播客阅读器，支持PWA，使用GitHub Actions运行。

高效、轻量的RSS/Atom 聚合器，并行抓取、解析和聚合多个RSS/Atom订阅源。支持并行抓取、分类管理、内容展示、播客收听，并能生成独立的 HTML 和 JSON 文件，方便部署和使用。

## **🏗️**功能概览

<details>
<summary>查看更多</summary>
| 顶栏模式-浅色模式  | 顶栏模式-暗色模式 |
| ------------- | ------------- |
| <img width="1815" height="917" alt="image" src="./public/img/image1.png" />  | <img width="1815" height="917" alt="image" src="./public/img/image2.png" />  |
：



</details>

##  **✨**主要功能变更

   本项目基于[avadhesh18/rssTea](https://github.com/avadhesh18/rssTea)构建，相比原项目，本项目主要包含以下变更：


- **🎨 双主题支持：**
  -  新增点击`主题`按钮，切换主题显示，内置两种 HTML 模板（顶栏主题和侧栏主题），默认主题是顶栏主题。
  - 优化订阅源主题样式，新增卡片排列方式。顶栏主题支持3列卡片显示，侧栏主题支持2列卡片显示。
- **📂 分类管理优化：** 
  - 订阅源自定义分类（如播客、新闻、博客等），改用YAML方式打造配置文件，便捷管理订阅源分类，将文章解析和分类分配两个步骤分开，使代码更清晰、更健壮。
  - 下拉框菜单新增分类显示（按`feeds.yaml`文件的订阅源顺序），支持输入搜索。
- **💡 今日文章高亮：** 自动为当天发布的文章添加`今日更新`标志，便于突出显示。
- **⚡ 返回顶部按钮：** 新增返回顶部按钮，一键复原。
- **🚀 高性能并行抓取：** 使用 `curl_multi` 并行处理所有订阅源，大幅提升抓取速度，避免重复解析同一个 Feed 的标题，并确保来自同一源的所有文章都使用相同的频道名。
- **🖼️ 智能图片处理:** 自动提取文章内嵌图片并修正相对路径。若无图片，则回退至网站Favicon，并配有加载默认占位符图片，确保视觉一致性。
- **🎧 沉浸式播客体验：** 全面兼容MP3、M4A、OGG等主流音频格式，播放控件自动嵌入，支持关闭控件，优化控件样式。通过预加载音频元数据，实现了自定义进度条和时长显示，在提供丰富交互的同时保证了极致的加载性能。
- **🗂️ 数据驱动：** 生成 `feed.json`（文章数据） 和 `channels.json` （频道分类）文件，便于前端实现动态筛选和交互。
- **🐛 重构项目结构：** 重新梳理了项目结构，将所有静态资源和生成文件置于 `public` 目录，实现了源码、配置与输出分离，。
- **📊 详细报告：** 工作流输出日志优化，清晰展示抓取进度、成功/失败源和最终统计信息，构建过程反馈更直观。
- **🔧 容错性强：** 自动跳过无效URL、网络错误和解析错误，并提供抓取失败的订阅源日志，不影响整体流程。

## 📁 项目结构

``` 
.
├── .github/
│   └── workflows/
│       └── rsswe.yml         # GitHub Actions 工作流配置文件
│
├── public/                   # 输出目录 (也是网站根目录)
│   ├── manifest.json         # PWA应用清单文件
│   ├── img/                  # 网站图标和资源
│   │   ├── 512.png           
│   │   ├── apple-touch-icon.png
│   │   ├── favicon.ico
│   │   ├── favicon.png
│   │   ├── loading.gif       # 加载占位图
│   │   ├── pause.png         # 音频暂停按钮
│   │   └── play_arrow.png   # 音频播放按钮
│   │
│   ├── static/               # 静态资源 
│   │   ├── theme.css         # 主样式表
│   │   └── theme.js          # 前端交互脚本 (筛选、音频播放等)
│   │
│   ├── index.html            # 生成的顶栏主题页面
│   ├── lindex.html           # 生成的侧栏主题页面 
│   ├── feed.json             # 所有文章数据 
│   └── channels.json         # 按分类组织的频道数据 
│
├── default.html              # 侧栏主题模板
├── feeds.php                 # 主程序脚本
├── feeds.yaml                # 订阅源配置文件
├── left.html                 # 顶栏主题模板
└── README.md                 
```
## ⚙️ 配置说明

在 `feeds.php` 文件中，修改下列配置项，改变每个订阅源获取的文章篇数：

```php
$postLimit = 5; // 每个订阅源获取的文章篇数
```

编辑 `feeds.yaml` 文件，按分类添加想要聚合的RSS/Atom URL。格式如下：

```yaml
# 支持播客、博客等URL，格式如下：
# 类别名称:
#  - url1
#  - url2
#  ......
分类1:
  - https://example1.com/feed1.rss
  - https://example2.com/feed2.atom
分类2:
  - https://another.com/feed.xml
```

> -   **分类名**: 将作为文章的 `category` 属性，可用于前端下拉框中的筛选。
> -   **URL列表**: 每个分类下可以包含一个或多个订阅源URL。
>

## 🚀 快速开始

### **Fork 本仓库:**

点击页面右上角的 Fork 按钮，将本仓库复制到你的`GitHub`账号下。

### **添加个人访问令牌（PAT）:**

点击自己的头像，选择 “Settings” -> “Developer settings” -> “Personal access tokens”->”Tokens（classic）“，点击 “Generate new token”，选择”Generate new token（classic）“，验证后，指定一个描述性名称，选择令牌的有效时间，选择要授予此令牌的范围或权限。只需要选择repo一项即可。点击”Generate token“，完成创建，创建完成后，复制保存token，后面要用。

![img](./public/img/image1.png)

### **添加Actions secrets：** 

在仓库的“Settings” -> “Secrets and variables” -> “Actions”。点击”Repository secrets“中的“New repository secret”，输入Actions secrets 的名字为`GH_PAT`（与`rsswe.yaml`文件中的保持一致），并将复制的token粘贴至Secrets框中，点击”Add secret“保存。

![img](./public/img/image2.png)

### **添加订阅源**

编辑 `feeds.yaml` 文件，自定义分类添加想要聚合的RSS/Atom URL。

### 启用工作流

在`.github/workflows/rsswe.yaml`中，取消`push`部分的注释，注释 `workflow_dispatch:`，启动工作流。

```yaml
on:
  push:
    branches:
      - main
  #手动执行工作流
 # workflow_dispatch:
```

在`发布至GitHub Pages`步骤中，添加推送仓库和对应分支即可。

```
      - name: 发布至GitHub Pages
        uses: JamesIves/github-pages-deploy-action@v4
        with:
          folder: public                   #构建生成的目录，默认为public
          repository-name: username/repo   #推送仓库
          branch: main                     #推送仓库的分支
          token: ${{ secrets.GH_PAT }} 
```

关于`JamesIves/github-pages-deploy-action@v4`更多配置参数，参考[JamesIves/github-pages-deploy-action#configuration-](https://github.com/JamesIves/github-pages-deploy-action#configuration-)。

## 🤔 常见问题 (FAQ)

**Q: 如何筛选某一个订阅源？**
A: 在`关注列表`下拉框中`输入搜索或下拉选择`即可。

**Q: 如何修改每个订阅源抓取的文章数量？**
A: 在PHP脚本中找到 `$postLimit = 5;` 这一行，将 `5` 修改为您想要的数字即可。

**Q: 为什么有些订阅源抓取失败？**
A: 脚本执行结束后会在工作流的运行终端输出详细的失败统计。常见原因包括：

-   **URL无效**: URL格式错误或无法访问。
-   **网络超时**: 目标服务器响应时间过长（脚本中设置为7秒）。
-   **HTTP错误**: 服务器返回了非200状态码（如404 Not Found, 403 Forbidden）。
-   **解析失败**: Feed格式不规范，或不是标准的RSS/Atom格式。

## 🙏 致谢

感谢原作者[avadhesh18](https://github.com/avadhesh18/rssTea)及所有贡献者的杰出工作和开源贡献。没有他们的基础，这个项目将无法存在。

## 📝 许可证

本项目采用GNU GPL许可证。
